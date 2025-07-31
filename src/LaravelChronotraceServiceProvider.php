<?php

namespace Grazulex\LaravelChronotrace;

use Grazulex\LaravelChronotrace\Commands\ListCommand;
use Grazulex\LaravelChronotrace\Commands\PurgeCommand;
use Grazulex\LaravelChronotrace\Commands\RecordCommand;
use Grazulex\LaravelChronotrace\Commands\ReplayCommand;
use Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware;
use Grazulex\LaravelChronotrace\Services\PIIScrubber;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
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
        }
    }
}
