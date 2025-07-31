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
        // Vérifier si ChronoTrace est activé
        if (! config('chronotrace.enabled', false)) {
            return $next($request);
        }

        // Vérifier si on doit capturer cette requête
        if (! $this->shouldCapture($request)) {
            return $next($request);
        }

        // Démarrer la capture (ultra-léger, juste marquage en mémoire)
        $traceId = $this->recorder->startCapture($request);

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

        return match ($mode) {
            'always' => true,
            'sample' => $this->shouldSample(),
            'targeted' => $this->isTargetedRoute($request),
            'record_on_error' => true, // On capture tout, on décidera plus tard si on stocke
            default => false,
        };
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
