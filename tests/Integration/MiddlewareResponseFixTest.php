<?php

declare(strict_types=1);

namespace Tests\Integration;

use Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;

/**
 * Tests de validation des corrections du middleware pour tous les types de réponses
 */
class MiddlewareResponseFixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('chronotrace.enabled', true);
        Config::set('chronotrace.mode', 'always');
        Config::set('chronotrace.debug', true);
        Config::set('chronotrace.async_storage', false); // Force synchrone pour les tests
    }

    /**
     * Test que le middleware accepte JsonResponse sans TypeError
     * Reproduit le bug original rapporté dans le test
     */
    public function test_middleware_accepts_json_response_without_type_error(): void
    {
        $traceRecorder = app(TraceRecorder::class);
        $middleware = new ChronoTraceMiddleware($traceRecorder);
        
        $request = Request::create('/api/test', 'GET');
        
        $next = function () {
            return new JsonResponse([
                'status' => 'success',
                'data' => ['id' => 123, 'name' => 'Test'],
                'meta' => ['timestamp' => time()]
            ], 200, ['Content-Type' => 'application/json']);
        };
        
        // Cette opération ne doit pas lever de TypeError
        $response = $middleware->handle($request, $next);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function test_middleware_accepts_redirect_response_without_type_error(): void
    {
        $traceRecorder = app(TraceRecorder::class);
        $middleware = new ChronoTraceMiddleware($traceRecorder);
        
        $request = Request::create('/login', 'POST');
        
        $next = function () {
            return new RedirectResponse('/dashboard', 302, [
                'Location' => '/dashboard',
                'Cache-Control' => 'no-cache'
            ]);
        };
        
        $response = $middleware->handle($request, $next);
        
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->isRedirect('/dashboard'));
    }

    public function test_middleware_accepts_standard_response_without_type_error(): void
    {
        $traceRecorder = app(TraceRecorder::class);
        $middleware = new ChronoTraceMiddleware($traceRecorder);
        
        $request = Request::create('/page', 'GET');
        
        $next = function () {
            return new Response(
                '<html><head><title>Test</title></head><body><h1>Hello World</h1></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        };
        
        $response = $middleware->handle($request, $next);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Hello World', $response->getContent());
    }

    public function test_middleware_works_with_custom_symfony_response(): void
    {
        $traceRecorder = app(TraceRecorder::class);
        $middleware = new ChronoTraceMiddleware($traceRecorder);
        
        $request = Request::create('/custom', 'GET');
        
        $next = function () {
            return new class extends \Symfony\Component\HttpFoundation\Response {
                public function __construct() {
                    parent::__construct('Custom Response Content', 201, [
                        'X-Custom-Header' => 'test-value',
                        'Content-Type' => 'text/plain'
                    ]);
                }
            };
        };
        
        $response = $middleware->handle($request, $next);
        
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Custom Response Content', $response->getContent());
        $this->assertEquals('test-value', $response->headers->get('X-Custom-Header'));
    }

    /**
     * Test de régression : s'assurer qu'on peut traiter plusieurs types de réponses de suite
     */
    public function test_middleware_handles_mixed_response_types_in_sequence(): void
    {
        $traceRecorder = app(TraceRecorder::class);
        $middleware = new ChronoTraceMiddleware($traceRecorder);
        
        // 1. JSON Response
        $jsonRequest = Request::create('/api/users', 'GET');
        $jsonNext = fn() => new JsonResponse(['users' => []], 200);
        $jsonResponse = $middleware->handle($jsonRequest, $jsonNext);
        $this->assertInstanceOf(JsonResponse::class, $jsonResponse);
        
        // 2. Redirect Response
        $redirectRequest = Request::create('/old-path', 'GET');
        $redirectNext = fn() => new RedirectResponse('/new-path', 301);
        $redirectResponse = $middleware->handle($redirectRequest, $redirectNext);
        $this->assertInstanceOf(RedirectResponse::class, $redirectResponse);
        
        // 3. HTML Response
        $htmlRequest = Request::create('/home', 'GET');
        $htmlNext = fn() => new Response('<h1>Home</h1>', 200);
        $htmlResponse = $middleware->handle($htmlRequest, $htmlNext);
        $this->assertInstanceOf(Response::class, $htmlResponse);
        
        // Tous doivent avoir passé sans erreur
        $this->assertTrue(true);
    }
}
