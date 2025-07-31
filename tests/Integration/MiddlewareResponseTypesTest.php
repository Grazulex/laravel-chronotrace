<?php

declare(strict_types=1);

namespace Tests\Integration;

use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;
use Override;

/**
 * Tests d'intégration pour vérifier la compatibilité avec les types de réponses Laravel
 * Reproduit le problème exact du TypeError avec JsonResponse
 */
class TraceRecorderResponseTypesTest extends TestCase
{
    private TraceRecorder $traceRecorder;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('chronotrace.enabled', true);
        Config::set('chronotrace.mode', 'sync');

        $this->traceRecorder = app(TraceRecorder::class);
    }

    /**
     * Test exact du cas qui causait le TypeError
     * Reproduit un workflow complet de capture avec JsonResponse
     */
    public function test_full_capture_cycle_with_json_response(): void
    {
        $request = Request::create('/api/test', 'GET', ['param' => 'value']);
        $request->headers->set('Accept', 'application/json');

        // Démarrage de la capture
        $traceId = $this->traceRecorder->startCapture($request);

        // Simulation d'un traitement (mesure du temps et mémoire)
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Simulation d'une réponse API typique
        $response = new JsonResponse([
            'status' => 'success',
            'data' => [
                'id' => 123,
                'name' => 'Test Item',
                'created_at' => now()->toISOString(),
            ],
            'meta' => [
                'version' => '1.0',
                'timestamp' => time(),
            ],
        ], 200, [
            'Content-Type' => 'application/json',
            'X-API-Version' => '1.0',
        ]);

        $duration = microtime(true) - $startTime;
        $memoryUsage = memory_get_usage(true) - $startMemory;

        // Ceci ne devrait plus lever de TypeError
        $this->traceRecorder->finishCapture($traceId, $response, $duration, $memoryUsage);

        $this->assertTrue(true); // Si on arrive ici, pas de TypeError
    }

    public function test_full_capture_cycle_with_redirect_response(): void
    {
        $request = Request::create('/login', 'POST', ['email' => 'test@example.com']);
        $traceId = $this->traceRecorder->startCapture($request);

        // Simulation d'une redirection après login
        $response = new RedirectResponse('/dashboard', 302, [
            'Location' => '/dashboard',
            'Set-Cookie' => 'session=abc123; HttpOnly',
        ]);

        $this->traceRecorder->finishCapture($traceId, $response, 0.05, 2048);

        $this->assertTrue(true);
    }

    public function test_full_capture_cycle_with_html_response(): void
    {
        $request = Request::create('/home', 'GET');
        $traceId = $this->traceRecorder->startCapture($request);

        // Simulation d'une réponse HTML
        $response = new Response(
            '<html><head><title>Home</title></head><body><h1>Welcome</h1></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );

        $this->traceRecorder->finishCapture($traceId, $response, 0.1, 4096);

        $this->assertTrue(true);
    }

    /**
     * Test de stress : plusieurs types de réponses en séquence
     */
    public function test_multiple_captures_with_different_response_types(): void
    {
        // 1. JSON API Response
        $jsonRequest = Request::create('/api/users', 'GET');
        $jsonTraceId = $this->traceRecorder->startCapture($jsonRequest);
        $jsonResponse = new JsonResponse(['users' => []]);
        $this->traceRecorder->finishCapture($jsonTraceId, $jsonResponse, 0.02, 1024);

        // 2. Redirect Response
        $redirectRequest = Request::create('/old-url', 'GET');
        $redirectTraceId = $this->traceRecorder->startCapture($redirectRequest);
        $redirectResponse = new RedirectResponse('/new-url', 301);
        $this->traceRecorder->finishCapture($redirectTraceId, $redirectResponse, 0.01, 512);

        // 3. HTML Response
        $htmlRequest = Request::create('/page', 'GET');
        $htmlTraceId = $this->traceRecorder->startCapture($htmlRequest);
        $htmlResponse = new Response('<h1>Page</h1>');
        $this->traceRecorder->finishCapture($htmlTraceId, $htmlResponse, 0.08, 2048);

        // 4. Custom Response
        $customRequest = Request::create('/custom', 'GET');
        $customTraceId = $this->traceRecorder->startCapture($customRequest);
        $customResponse = new class extends \Symfony\Component\HttpFoundation\Response
        {
            public function __construct()
            {
                parent::__construct('Custom Content', 200, ['X-Custom' => 'true']);
            }
        };
        $this->traceRecorder->finishCapture($customTraceId, $customResponse, 0.03, 768);

        $this->assertTrue(true); // Si toutes les captures passent, le problème est résolu
    }
}
