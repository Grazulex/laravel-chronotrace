<?php

namespace Grazulex\LaravelChronotrace;

use Exception;
use Grazulex\LaravelChronotrace\Commands\DiagnoseCommand;
use Grazulex\LaravelChronotrace\Commands\InstallCommand;
use Grazulex\LaravelChronotrace\Commands\ListCommand;
use Grazulex\LaravelChronotrace\Commands\MiddlewareTestCommand;
use Grazulex\LaravelChronotrace\Commands\PurgeCommand;
use Grazulex\LaravelChronotrace\Commands\RecordCommand;
use Grazulex\LaravelChronotrace\Commands\ReplayCommand;
use Grazulex\LaravelChronotrace\Listeners\CacheEventListener;
use Grazulex\LaravelChronotrace\Listeners\DatabaseEventListener;
use Grazulex\LaravelChronotrace\Listeners\HttpEventListener;
use Grazulex\LaravelChronotrace\Listeners\QueueEventListener;
use Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware;
use Grazulex\LaravelChronotrace\Services\PIIScrubber;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider;
use Override;

class LaravelChronotraceServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/chronotrace.php', 'chronotrace');

        // Enregistrer les services
        $this->app->singleton(PIIScrubber::class);
        $this->app->singleton(TraceRecorder::class);
        $this->app->singleton(TraceStorage::class, function ($app): TraceStorage {
            $storage = config('chronotrace.storage', 'local');
            $compression = config('chronotrace.compression.enabled', true);

            // Mapper le type de stockage vers le disk Laravel approprié
            $disk = match ($storage) {
                's3' => $this->configureS3Disk(),
                'local' => 'local',
                default => 'local'
            };

            return new TraceStorage(
                $disk,
                is_bool($compression) ? $compression : true
            );
        });

        // Enregistrer les event listeners
        $this->app->singleton(DatabaseEventListener::class);
        $this->app->singleton(CacheEventListener::class);
        $this->app->singleton(HttpEventListener::class);
        $this->app->singleton(QueueEventListener::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/chronotrace.php' => config_path('chronotrace.php'),
            ], 'chronotrace-config');

            $this->commands([
                InstallCommand::class,
                RecordCommand::class,
                ReplayCommand::class,
                ListCommand::class,
                PurgeCommand::class,
                DiagnoseCommand::class,
                MiddlewareTestCommand::class,
            ]);
        }

        // Enregistrer le middleware si ChronoTrace est activé
        if (config('chronotrace.enabled', false)) {
            // Laravel 11+ : Recommander l'enregistrement manuel dans bootstrap/app.php
            // Mais essayer quand même l'ancienne méthode pour compatibilité
            try {
                $router = $this->app->make('router');
                $router->pushMiddlewareToGroup('web', ChronoTraceMiddleware::class);
                $router->pushMiddlewareToGroup('api', ChronoTraceMiddleware::class);
            } catch (Exception) {
                // Échec silencieux - l'utilisateur devra configurer manuellement
                // dans bootstrap/app.php pour Laravel 11+
            }

            // Enregistrer les event listeners
            $this->registerEventListeners();
        }
    }

    /**
     * Enregistre les event listeners pour capturer les événements Laravel
     */
    private function registerEventListeners(): void
    {
        $events = $this->app->make('events');

        // Database events
        if (config('chronotrace.capture.database', true)) {
            $events->listen(QueryExecuted::class, [DatabaseEventListener::class, 'handleQueryExecuted']);
            $events->listen(TransactionBeginning::class, [DatabaseEventListener::class, 'handleTransactionBeginning']);
            $events->listen(TransactionCommitted::class, [DatabaseEventListener::class, 'handleTransactionCommitted']);
            $events->listen(TransactionRolledBack::class, [DatabaseEventListener::class, 'handleTransactionRolledBack']);
        }

        // Cache events
        if (config('chronotrace.capture.cache', true)) {
            $events->listen(CacheHit::class, [CacheEventListener::class, 'handleCacheHit']);
            $events->listen(CacheMissed::class, [CacheEventListener::class, 'handleCacheMissed']);
            $events->listen(KeyWritten::class, [CacheEventListener::class, 'handleKeyWritten']);
            $events->listen(KeyForgotten::class, [CacheEventListener::class, 'handleKeyForgotten']);
        }

        // HTTP events (Laravel HTTP Client)
        if (config('chronotrace.capture.http', true)) {
            $events->listen(RequestSending::class, [HttpEventListener::class, 'handleRequestSending']);
            $events->listen(ResponseReceived::class, [HttpEventListener::class, 'handleResponseReceived']);
            $events->listen(ConnectionFailed::class, [HttpEventListener::class, 'handleConnectionFailed']);
        }

        // Queue/Job events
        if (config('chronotrace.capture.jobs', true)) {
            $events->listen(JobProcessing::class, [QueueEventListener::class, 'handleJobProcessing']);
            $events->listen(JobProcessed::class, [QueueEventListener::class, 'handleJobProcessed']);
            $events->listen(JobFailed::class, [QueueEventListener::class, 'handleJobFailed']);
        }
    }

    /**
     * Configure le disk S3/MinIO pour ChronoTrace
     */
    private function configureS3Disk(): string
    {
        $config = config('chronotrace.s3', []);

        // Configuration du disk S3 pour ChronoTrace
        config(['filesystems.disks.chronotrace_s3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => $config['region'] ?? 'us-east-1',
            'bucket' => $config['bucket'] ?? 'chronotrace',
            'url' => $config['endpoint'] ?? null,
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => ! empty($config['endpoint']), // Pour MinIO
            'root' => $config['path_prefix'] ?? 'traces',
            'visibility' => 'private',
        ]]);

        return 'chronotrace_s3';
    }
}
