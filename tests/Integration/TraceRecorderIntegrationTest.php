<?php

namespace Grazulex\LaravelChronotrace\Tests\Integration;

use Exception;
use Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider;
use Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Orchestra\Testbench\TestCase;

class TraceRecorderIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelChronotraceServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('chronotrace.enabled', true);
        $app['config']->set('chronotrace.mode', 'always');
        $app['config']->set('chronotrace.async_storage', false); // Test synchrone
        $app['config']->set('chronotrace.storage.disk', 'local');
        $app['config']->set('chronotrace.storage.path', 'tests/traces');
        $app['config']->set('chronotrace.scrub', ['password', 'token']);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Si on avait des migrations pour ChronoTrace
    }

    public function test_trace_recorder_can_be_resolved(): void
    {
        $recorder = $this->app->make(TraceRecorder::class);

        $this->assertInstanceOf(TraceRecorder::class, $recorder);
    }

    public function test_trace_storage_can_be_resolved(): void
    {
        $storage = $this->app->make(TraceStorage::class);

        $this->assertInstanceOf(TraceStorage::class, $storage);
    }

    public function test_can_start_and_finish_trace_capture(): void
    {
        $recorder = $this->app->make(TraceRecorder::class);

        // Créer une fausse requête
        $request = Request::create('/test', 'GET', ['param' => 'value']);
        $request->headers->set('User-Agent', 'TestAgent/1.0');

        // Démarrer la capture
        $traceId = $recorder->startCapture($request);

        $this->assertNotEmpty($traceId);
        $this->assertStringStartsWith('ct_', $traceId);

        // Vérifier que le traceId est en instance
        $currentTrace = $this->app->make('chronotrace.current_trace');
        $this->assertSame($traceId, $currentTrace);

        // Créer une fausse réponse
        $response = new Response('{"status": "ok"}', 200, ['Content-Type' => 'application/json']);

        // Finaliser la capture
        $recorder->finishCapture($traceId, $response, 0.1, 1024000);

        // Vérifier que l'instance a été nettoyée
        $this->expectException(BindingResolutionException::class);
        $this->app->make('chronotrace.current_trace');
    }

    public function test_can_capture_with_exception(): void
    {
        $recorder = $this->app->make(TraceRecorder::class);

        $request = Request::create('/error', 'GET');
        $traceId = $recorder->startCapture($request);

        $exception = new Exception('Test exception', 500);

        $recorder->finishCaptureWithException($traceId, $exception, 0.2, 2048000);

        // Devrait avoir stocké la trace avec exception
        $this->assertTrue(true); // Test basique pour l'instant
    }

    public function test_can_add_captured_data(): void
    {
        $recorder = $this->app->make(TraceRecorder::class);

        $request = Request::create('/test', 'GET');
        $traceId = $recorder->startCapture($request);

        // Ajouter des données capturées
        $recorder->addCapturedData('database', [
            'query' => 'SELECT * FROM users',
            'time' => 0.001,
            'bindings' => [],
        ]);

        $recorder->addCapturedData('cache', [
            'operation' => 'get',
            'key' => 'user:1',
            'hit' => true,
        ]);

        $response = new Response('OK', 200);
        $recorder->finishCapture($traceId, $response, 0.1, 1024000);

        $this->assertTrue(true); // Test basique pour l'instant
    }

    public function test_configuration_is_loaded(): void
    {
        $this->assertTrue(config('chronotrace.enabled'));
        $this->assertSame('always', config('chronotrace.mode'));
        $this->assertFalse(config('chronotrace.async_storage'));
        $this->assertIsArray(config('chronotrace.scrub'));
        $this->assertContains('password', config('chronotrace.scrub'));
    }

    public function test_middleware_is_registered(): void
    {
        $router = $this->app->make('router');

        // Vérifier que le middleware est enregistré
        $middleware = $router->getMiddlewareGroups();

        $this->assertArrayHasKey('web', $middleware);
        $this->assertArrayHasKey('api', $middleware);

        // Le middleware ChronoTrace devrait être dans les groupes
        $this->assertContains(
            ChronoTraceMiddleware::class,
            $middleware['web']
        );
    }
}
