<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Middleware;

use Closure;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Middleware ultra-léger pour capturer les traces ChronoTrace
 * Impact minimal sur les performances - capture async
 */
class ChronoTraceMiddleware
{
    public function __construct(
        private readonly TraceRecorder $recorder
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Debug: Toujours logger que le middleware est appelé
        if (config('chronotrace.debug', false)) {
            error_log("ChronoTrace Middleware: Called for {$request->method()} {$request->fullUrl()}");
        }

        // Vérifier si ChronoTrace est activé
        if (! config('chronotrace.enabled', false)) {
            if (config('chronotrace.debug', false)) {
                error_log('ChronoTrace Middleware: Disabled by config');
            }

            return $next($request);
        }

        // Vérifier si on doit capturer cette requête
        if (! $this->shouldCapture($request)) {
            if (config('chronotrace.debug', false)) {
                error_log('ChronoTrace Middleware: Request not captured due to shouldCapture() = false');
            }

            return $next($request);
        }

        if (config('chronotrace.debug', false)) {
            error_log('ChronoTrace Middleware: Starting capture');
        }

        // Démarrer la capture (ultra-léger, juste marquage en mémoire)
        $traceId = $this->recorder->startCapture($request);

        if (config('chronotrace.debug', false)) {
            error_log("ChronoTrace Middleware: Started capture with traceId: {$traceId}");
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            /** @var SymfonyResponse $response */
            $response = $next($request);

            // Calculer les métriques
            $duration = microtime(true) - $startTime;
            $memoryUsage = memory_get_usage(true) - $startMemory;

            // Finaliser la capture (toujours léger)
            $this->recorder->finishCapture($traceId, $response, $duration, $memoryUsage);

            return $response;
        } catch (Throwable $exception) {
            // En cas d'exception, capturer avec l'erreur
            $duration = microtime(true) - $startTime;
            $memoryUsage = memory_get_usage(true) - $startMemory;

            $this->recorder->finishCaptureWithException($traceId, $exception, $duration, $memoryUsage);

            throw $exception;
        }
    }

    /**
     * Détermine si cette requête doit être capturée
     */
    private function shouldCapture(Request $request): bool
    {
        $mode = config('chronotrace.mode', 'record_on_error');

        if (config('chronotrace.debug', false)) {
            error_log("ChronoTrace Middleware: shouldCapture() mode: {$mode}");
        }

        $result = match ($mode) {
            'always' => true,
            'sample' => $this->shouldSample(),
            'targeted' => $this->isTargetedRoute($request),
            'record_on_error' => true, // On capture tout, on décidera plus tard si on stocke
            default => false,
        };

        if (config('chronotrace.debug', false)) {
            $resultStr = $result ? 'true' : 'false';
            error_log("ChronoTrace Middleware: shouldCapture() result: {$resultStr}");
        }

        return $result;
    }

    /**
     * Échantillonnage probabiliste
     */
    private function shouldSample(): bool
    {
        $sampleRate = config('chronotrace.sample_rate', 0.001);

        return mt_rand() / mt_getrandmax() < $sampleRate;
    }

    /**
     * Vérifie si la route est ciblée
     */
    private function isTargetedRoute(Request $request): bool
    {
        $targetRoutes = config('chronotrace.targets.routes', []);
        if (! is_array($targetRoutes)) {
            return false;
        }

        $currentRoute = $request->path();

        foreach ($targetRoutes as $pattern) {
            if (is_string($pattern) && fnmatch($pattern, $currentRoute)) {
                return true;
            }
        }

        return false;
    }
}
