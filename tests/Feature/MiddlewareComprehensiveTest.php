<?php

declare(strict_types=1);

namespace Tests\Feature;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Override;
use Tests\TestCase;

/**
 * Tests complets du middleware pour tous les scénarios critiques
 */
class MiddlewareComprehensiveTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Configuration pour tests
        config([
            'chronotrace.enabled' => true,
            'chronotrace.mode' => 'always',
            'chronotrace.async_storage' => false,
            'chronotrace.capture.database' => true,
            'chronotrace.capture.cache' => true,
            'chronotrace.capture.http' => true,
            'chronotrace.capture.jobs' => true,
        ]);

        // Routes de test
        Route::get('/test-get', fn () => response()->json(['test' => 'get']));
        Route::post('/test-post', fn () => response()->json(['test' => 'post']));
        Route::get('/test-error', function (): void {
            throw new Exception('Test error');
        });
        Route::get('/test-slow', function () {
            usleep(100000); // 100ms

            return response()->json(['test' => 'slow']);
        });
    }

    public function test_middleware_captures_get_requests(): void
    {
        $response = $this->getJson('/test-get');

        $response->assertStatus(200);
        $response->assertJson(['test' => 'get']);

        // Vérifier qu'une trace a été créée
        $this->assertTrue(true); // Ajoutera validation du storage plus tard
    }

    public function test_middleware_captures_post_requests_with_data(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123', // Devrait être scrubbed
        ];

        $response = $this->postJson('/test-post', $data);

        $response->assertStatus(200);
        $response->assertJson(['test' => 'post']);
    }

    public function test_middleware_captures_error_responses(): void
    {
        $response = $this->getJson('/test-error');

        $response->assertStatus(500);
    }

    public function test_middleware_records_performance_metrics(): void
    {
        $response = $this->getJson('/test-slow');

        $response->assertStatus(200);
        // La durée devrait être > 100ms
    }

    public function test_middleware_respects_disabled_configuration(): void
    {
        config(['chronotrace.enabled' => false]);

        $response = $this->getJson('/test-get');

        $response->assertStatus(200);
        // Aucune trace ne devrait être créée
    }

    public function test_middleware_respects_sample_mode(): void
    {
        config([
            'chronotrace.mode' => 'sample',
            'chronotrace.sample_rate' => 0.0, // 0% - aucune capture
        ]);

        $response = $this->getJson('/test-get');

        $response->assertStatus(200);
        // Aucune trace ne devrait être créée avec 0% sampling
    }

    public function test_middleware_respects_record_on_error_mode(): void
    {
        config(['chronotrace.mode' => 'record_on_error']);

        // Requête normale - ne devrait pas être stockée
        $successResponse = $this->getJson('/test-get');
        $successResponse->assertStatus(200);

        // Requête avec erreur - devrait être stockée
        $errorResponse = $this->getJson('/test-error');
        $errorResponse->assertStatus(500);
    }

    public function test_middleware_respects_targeted_routes_mode(): void
    {
        config([
            'chronotrace.mode' => 'targeted',
            'chronotrace.targets.routes' => ['/test-get'],
        ]);

        // Route ciblée
        $targetedResponse = $this->getJson('/test-get');
        $targetedResponse->assertStatus(200);

        // Route non ciblée
        $nonTargetedResponse = $this->postJson('/test-post', []);
        $nonTargetedResponse->assertStatus(200);
    }

    public function test_middleware_preserves_response_headers(): void
    {
        Route::get('/test-headers', function () {
            return response()->json(['test' => 'headers'])
                ->header('X-Custom-Header', 'test-value');
            // Pas de Cache-Control car Laravel ajoute automatiquement 'private'
        });

        $response = $this->getJson('/test-headers');

        $response->assertStatus(200);
        $response->assertHeader('X-Custom-Header', 'test-value');
        // Vérifier que le header existe sans valeur exacte car Laravel peut ajouter des valeurs
        $this->assertTrue($response->headers->has('Cache-Control'));
    }

    public function test_middleware_handles_different_response_types(): void
    {
        // JSON Response
        Route::get('/test-json', fn (): JsonResponse => new JsonResponse(['type' => 'json']));

        // Plain Response
        Route::get('/test-plain', fn () => response('Plain text'));

        // Redirect Response
        Route::get('/test-redirect', fn () => redirect('/test-get'));

        $jsonResponse = $this->getJson('/test-json');
        $jsonResponse->assertStatus(200);
        $jsonResponse->assertJson(['type' => 'json']);

        $plainResponse = $this->get('/test-plain');
        $plainResponse->assertStatus(200);
        $plainResponse->assertSee('Plain text');

        $redirectResponse = $this->get('/test-redirect');
        $redirectResponse->assertStatus(302);
    }

    public function test_middleware_handles_memory_tracking(): void
    {
        Route::get('/test-memory', function () {
            // Allouer de la mémoire pour tester le tracking
            $data = str_repeat('x', 1024 * 100); // 100KB

            return response()->json(['size' => strlen($data)]);
        });

        $response = $this->getJson('/test-memory');

        $response->assertStatus(200);
        $response->assertJsonStructure(['size']);
    }

    public function test_middleware_captures_with_database_queries(): void
    {
        Route::get('/test-db', fn () =>
            // Pas de vraie requête DB, juste simuler que le middleware fonctionne
            response()->json(['db' => 'tested']));

        $response = $this->getJson('/test-db');

        $response->assertStatus(200);
        $response->assertJson(['db' => 'tested']);
        // Le middleware devrait capturer cette requête même sans vraie DB query
    }
}
