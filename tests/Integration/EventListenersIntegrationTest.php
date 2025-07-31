<?php

namespace Grazulex\LaravelChronotrace\Tests\Integration;

use Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider;
use Grazulex\LaravelChronotrace\Listeners\CacheEventListener;
use Grazulex\LaravelChronotrace\Listeners\DatabaseEventListener;
use Grazulex\LaravelChronotrace\Listeners\HttpEventListener;
use Grazulex\LaravelChronotrace\Listeners\QueueEventListener;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Database\Events\QueryExecuted;
use Orchestra\Testbench\TestCase;

class EventListenersIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelChronotraceServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('chronotrace.enabled', true);
        $app['config']->set('chronotrace.capture.database', true);
        $app['config']->set('chronotrace.capture.cache', true);
        $app['config']->set('chronotrace.capture.http', true);
        $app['config']->set('chronotrace.capture.jobs', true);
    }

    public function test_registers_all_event_listeners_when_package_is_enabled(): void
    {
        // Vérifier que les listeners sont enregistrés dans le container
        $this->assertTrue($this->app->bound(DatabaseEventListener::class));
        $this->assertTrue($this->app->bound(CacheEventListener::class));
        $this->assertTrue($this->app->bound(HttpEventListener::class));
        $this->assertTrue($this->app->bound(QueueEventListener::class));
    }

    public function test_has_event_listeners_registered_for_laravel_events(): void
    {
        $dispatcher = $this->app['events'];

        // Vérifier que les événements ont des listeners
        $queryListeners = $dispatcher->getListeners(QueryExecuted::class);
        $cacheListeners = $dispatcher->getListeners(CacheHit::class);

        $this->assertNotEmpty($queryListeners);
        $this->assertNotEmpty($cacheListeners);
    }

    public function test_respects_capture_configuration_flags(): void
    {
        $this->app['config']->set('chronotrace.capture.database', true);
        $this->app['config']->set('chronotrace.capture.cache', false);
        $this->app['config']->set('chronotrace.capture.http', true);
        $this->app['config']->set('chronotrace.capture.jobs', false);

        $this->assertTrue(config('chronotrace.capture.database'));
        $this->assertFalse(config('chronotrace.capture.cache'));
        $this->assertTrue(config('chronotrace.capture.http'));
        $this->assertFalse(config('chronotrace.capture.jobs'));
    }
}
