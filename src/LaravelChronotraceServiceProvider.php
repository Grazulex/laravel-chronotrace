<?php

namespace Grazulex\LaravelChronotrace;

use Illuminate\Support\ServiceProvider;

class LaravelChronotraceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/chronotrace.php', 'chronotrace');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/chronotrace.php' => config_path('chronotrace.php'),
            ], 'chronotrace-config');

            $this->commands([
                //
            ]);
        }
    }
}
