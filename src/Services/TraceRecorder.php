<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Services;

use Exception;
use Grazulex\LaravelChronotrace\Jobs\StoreTraceJob;
use Grazulex\LaravelChronotrace\Models\TraceContext;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Models\TraceRequest;
use Grazulex\LaravelChronotrace\Models\TraceResponse;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Service de capture ultra-performant pour ChronoTrace
 * Capture en mémoire, stockage asynchrone
 */
class TraceRecorder
{
    /** @var array<string, array> Traces actives en mémoire */
    private array $activeTraces = [];

    public function __construct(
        private readonly Application $app,
        private readonly PIIScrubber $scrubber
    ) {}

    /**
     * Démarre la capture d'une trace (ultra-léger)
     */
    public function startCapture(Request $request): string
    {
        $traceId = $this->generateTraceId();

        // Capture ultra-légère - juste les données essentielles
        $this->activeTraces[$traceId] = [
            'start_time' => microtime(true),
            'request' => $this->captureRequest($request),
            'captured_data' => [
                'database' => [],
                'cache' => [],
                'http' => [],
                'mail' => [],
                'notifications' => [],
                'events' => [],
                'jobs' => [],
                'filesystem' => [],
            ],
        ];

        // Enregistrer ce traceId pour les listeners
        $this->app->instance('chronotrace.current_trace', $traceId);

        return $traceId;
    }

    /**
     * Finalise la capture normale
     */
    public function finishCapture(string $traceId, Response $response, float $duration, int $memoryUsage): void
    {
        if (! isset($this->activeTraces[$traceId])) {
            return;
        }

        $trace = $this->activeTraces[$traceId];

        // Capturer la réponse
        $traceResponse = $this->captureResponse($response, $duration, $memoryUsage);

        // Décider si on doit stocker selon le mode et le statut
        if ($this->shouldStore($traceResponse->status)) {
            $this->storeTrace($traceId, $trace, $traceResponse);
        }

        // Nettoyer
        unset($this->activeTraces[$traceId]);
        $this->app->forgetInstance('chronotrace.current_trace');
    }

    /**
     * Finalise la capture avec exception (toujours stockée)
     */
    public function finishCaptureWithException(string $traceId, Throwable $exception, float $duration, int $memoryUsage): void
    {
        if (! isset($this->activeTraces[$traceId])) {
            return;
        }

        $trace = $this->activeTraces[$traceId];

        // Créer une réponse avec l'exception
        $traceResponse = new TraceResponse(
            status: 500,
            headers: [],
            content: '',
            duration: $duration,
            memoryUsage: $memoryUsage,
            timestamp: microtime(true),
            exception: $this->formatException($exception),
            cookies: [],
        );

        // Toujours stocker les traces avec exception
        $this->storeTrace($traceId, $trace, $traceResponse);

        // Nettoyer
        unset($this->activeTraces[$traceId]);
        $this->app->forgetInstance('chronotrace.current_trace');
    }

    /**
     * Ajoute des données capturées par les listeners
     *
     * @param  array<string, mixed>  $data
     */
    public function addCapturedData(string $type, array $data): void
    {
        try {
            $traceId = $this->app->make('chronotrace.current_trace');
        } catch (Exception) {
            return; // Pas de trace active
        }

        if ($traceId && is_string($traceId) && isset($this->activeTraces[$traceId])) {
            $this->activeTraces[$traceId]['captured_data'][$type][] = $data;
        }
    }

    private function generateTraceId(): string
    {
        return 'ct_' . Str::random(16) . '_' . time();
    }

    private function captureRequest(Request $request): TraceRequest
    {
        // Scrubber appliqué ici pour éviter de stocker des données sensibles
        $headers = $this->scrubber->scrubArray($request->headers->all());
        $input = $this->scrubber->scrubArray($request->input());

        // Capturer la session en toute sécurité
        $session = [];
        try {
            if ($request->hasSession()) {
                $session = $this->scrubber->scrubArray($request->session()->all());
            }
        } catch (RuntimeException) {
            // Session non configurée - continuer sans session
        }

        return new TraceRequest(
            method: $request->method(),
            url: $request->fullUrl(),
            headers: $headers,
            query: $request->query->all(),
            input: $input,
            files: $this->captureFiles($request),
            user: $this->captureUser($request),
            session: $session,
            userAgent: $request->userAgent() ?? '',
            ip: $request->ip() ?? '',
            timestamp: microtime(true),
        );
    }

    private function captureResponse(Response $response, float $duration, int $memoryUsage): TraceResponse
    {
        $content = $response->getContent();

        if ($content === false) {
            $content = '';
        }

        // Limiter la taille du contenu capturé
        $maxContentSize = 1024 * 1024; // 1MB
        if (strlen($content) > $maxContentSize) {
            $content = substr($content, 0, $maxContentSize) . '[... truncated]';
        }

        $scrubbedContent = $this->scrubber->scrubString($content);

        return new TraceResponse(
            status: $response->getStatusCode(),
            headers: $response->headers->all(),
            content: $scrubbedContent,
            duration: $duration,
            memoryUsage: $memoryUsage,
            timestamp: microtime(true),
            exception: null,
            cookies: [],
        );
    }

    private function captureFiles(Request $request): array
    {
        $files = [];
        foreach ($request->allFiles() as $key => $file) {
            if (is_array($file)) {
                $files[$key] = array_map(fn ($f): array => [
                    'name' => $f->getClientOriginalName(),
                    'size' => $f->getSize(),
                    'mime' => $f->getMimeType(),
                ], $file);
            } else {
                $files[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ];
            }
        }

        return $files;
    }

    private function captureUser(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return $this->scrubber->scrubArray([
            'id' => $user->getKey(),
            'class' => $user::class,
            'attributes' => $user->toArray(),
        ]);
    }

    private function captureContext(): TraceContext
    {
        return new TraceContext(
            laravel_version: $this->app->version(),
            php_version: PHP_VERSION,
            config: $this->captureRelevantConfig(),
            env_vars: $this->captureRelevantEnvVars(),
            git_commit: $this->getGitCommit(),
            branch: $this->getGitBranch(),
            packages: [],
            middlewares: [],
            providers: [],
        );
    }

    private function shouldStore(int $statusCode): bool
    {
        $mode = config('chronotrace.mode', 'record_on_error');

        return match ($mode) {
            'always' => true,
            'sample' => true, // Déjà décidé dans le middleware
            'targeted' => true, // Déjà décidé dans le middleware
            'record_on_error' => $statusCode >= 500,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $trace
     */
    private function storeTrace(string $traceId, array $trace, TraceResponse $response): void
    {
        if (! isset($trace['request']) || ! ($trace['request'] instanceof TraceRequest)) {
            return;
        }

        $traceData = new TraceData(
            traceId: $traceId,
            timestamp: date('c'),
            environment: $this->app->environment(),
            request: $trace['request'],
            response: $response,
            context: $this->captureContext(),
            database: $trace['captured_data']['database'],
            cache: $trace['captured_data']['cache'],
            http: $trace['captured_data']['http'],
            mail: $trace['captured_data']['mail'],
            notifications: $trace['captured_data']['notifications'],
            events: $trace['captured_data']['events'],
            jobs: $trace['captured_data']['jobs'],
            filesystem: $trace['captured_data']['filesystem'],
        );

        // Stockage asynchrone via queue pour ne pas ralentir la réponse
        if (config('chronotrace.async_storage', true)) {
            $connection = config('chronotrace.queue_connection', 'default');
            if (is_string($connection)) {
                Queue::connection($connection)
                    ->pushOn(config('chronotrace.queue_name', 'chronotrace'), new StoreTraceJob($traceData));
            }
        } else {
            // Stockage synchrone (dev/debug uniquement)
            app(TraceStorage::class)->store($traceData);
        }
    }

    private function formatException(Throwable $exception): string
    {
        $exceptionData = [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        $result = json_encode($exceptionData, JSON_PRETTY_PRINT);

        return $result !== false ? $result : '{"error": "Failed to encode exception"}';
    }

    private function captureRelevantConfig(): array
    {
        // Capturer seulement la config pertinente, pas tout
        return [
            'app.debug' => config('app.debug'),
            'app.env' => config('app.env'),
            'database.default' => config('database.default'),
            'cache.default' => config('cache.default'),
            'queue.default' => config('queue.default'),
        ];
    }

    private function captureRelevantEnvVars(): array
    {
        $relevantVars = ['APP_ENV', 'APP_DEBUG', 'DB_CONNECTION', 'CACHE_DRIVER', 'QUEUE_CONNECTION'];
        $envVars = [];

        foreach ($relevantVars as $var) {
            $envVars[$var] = env($var);
        }

        return $this->scrubber->scrubArray($envVars);
    }

    private function getGitCommit(): string
    {
        if (file_exists(base_path('.git/HEAD'))) {
            $head = file_get_contents(base_path('.git/HEAD'));
            if ($head === false) {
                return '';
            }
            $head = trim($head);
            if (str_starts_with($head, 'ref: ')) {
                $ref = substr($head, 5);
                $commitFile = base_path('.git/' . $ref);
                if (file_exists($commitFile)) {
                    $commit = file_get_contents($commitFile);

                    return $commit !== false ? trim($commit) : '';
                }
            }
        }

        return '';
    }

    private function getGitBranch(): string
    {
        if (file_exists(base_path('.git/HEAD'))) {
            $head = file_get_contents(base_path('.git/HEAD'));
            if ($head === false) {
                return '';
            }
            $head = trim($head);
            if (str_starts_with($head, 'ref: refs/heads/')) {
                return substr($head, 16);
            }
        }

        return '';
    }
}
