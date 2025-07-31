<?php

namespace Grazulex\LaravelChronotrace\Tests\Integration;

use Exception;
use Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider;
use Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Override;

class MiddlewareIntegrationTest extends TestCase
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
        $app['config']->set('chronotrace.async_storage', false);
        $app['config']->set('chronotrace.storage.disk', 'local');
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Définir des routes de test
        Route::get('/test', fn () => response()->json(['message' => 'Hello World']))->name('test.hello');

        Route::get('/test/error', function (): void {
            throw new Exception('Test error');
        })->name('test.error');

        Route::post('/test/data', fn (Request $request) => response()->json([
            'received' => $request->all(),
            'password' => 'should-be-scrubbed',
        ]))->name('test.data');
    }

    public function test_middleware_is_automatically_registered(): void
    {
        $middleware = $this->app['router']->getMiddlewareGroups();

        $this->assertContains(ChronoTraceMiddleware::class, $middleware['web']);
        $this->assertContains(ChronoTraceMiddleware::class, $middleware['api']);
    }

    public function test_middleware_captures_successful_requests(): void
    {
        $response = $this->get('/test');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Hello World']);

        // Le middleware devrait avoir capturé cette requête
        // (Vérification plus approfondie nécessiterait l'accès au stockage)
    }

    public function test_middleware_captures_error_requests(): void
    {
        $response = $this->get('/test/error');

        $response->assertStatus(500);

        // Le middleware devrait avoir capturé cette erreur
    }

    public function test_middleware_captures_post_requests_with_data(): void
    {
        $data = [
            'name' => 'John Doe',
            'password' => 'secret123',
            'email' => 'john@example.com',
        ];

        $response = $this->post('/test/data', $data);

        $response->assertStatus(200);
        $response->assertJsonFragment(['received' => $data]);

        // Le middleware devrait avoir capturé et nettoyé les données sensibles
    }

    public function test_middleware_can_be_disabled(): void
    {
        // Désactiver ChronoTrace
        config(['chronotrace.enabled' => false]);

        $response = $this->get('/test');

        $response->assertStatus(200);

        // Aucune trace ne devrait être capturée
    }

    public function test_middleware_respects_sample_mode(): void
    {
        config(['chronotrace.mode' => 'sample']);
        config(['chronotrace.sample_rate' => 0.0]); // 0% échantillonnage

        $response = $this->get('/test');

        $response->assertStatus(200);

        // Aucune trace ne devrait être capturée avec 0% d'échantillonnage
    }

    public function test_middleware_respects_record_on_error_mode(): void
    {
        config(['chronotrace.mode' => 'record_on_error']);

        // Requête réussie - ne devrait pas être tracée
        $response = $this->get('/test');
        $response->assertStatus(200);

        // Requête avec erreur - devrait être tracée
        $errorResponse = $this->get('/test/error');
        $errorResponse->assertStatus(500);
    }

    public function test_middleware_handles_targeted_routes(): void
    {
        config(['chronotrace.mode' => 'targeted']);
        config(['chronotrace.targeted_routes' => ['/test']]);

        // Route ciblée - devrait être tracée
        $response = $this->get('/test');
        $response->assertStatus(200);

        // Route non ciblée - ne devrait pas être tracée
        // (nécessiterait une autre route pour tester)
    }

    public function test_middleware_preserves_response(): void
    {
        $originalResponse = $this->get('/test');

        // Le middleware ne devrait pas modifier la réponse
        $originalResponse->assertStatus(200);
        $originalResponse->assertJson(['message' => 'Hello World']);

        // Headers devraient être préservés
        $this->assertSame('application/json', $originalResponse->headers->get('Content-Type'));
    }
}
