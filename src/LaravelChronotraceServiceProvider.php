<?php

namespace Grazulex\LaravelChronotrace;

use Grazulex\LaravelChronotrace\Commands\ListCommand;
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
            $disk = config('chronotrace.storage.disk', 'local');
            $compression = config('chronotrace.storage.compression', true);

            return new TraceStorage(
                is_string($disk) ? $disk : 'local',
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
                RecordCommand::class,
                ReplayCommand::class,
                ListCommand::class,
                PurgeCommand::class,
            ]);
        }

        // Enregistrer le middleware si ChronoTrace est activé
        if (config('chronotrace.enabled', false)) {
            $this->app->make('router')->pushMiddlewareToGroup('web', ChronoTraceMiddleware::class);
            $this->app->make('router')->pushMiddlewareToGroup('api', ChronoTraceMiddleware::class);

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
}
