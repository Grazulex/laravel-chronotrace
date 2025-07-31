<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Tests\Unit;

use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;
use Override;

/**
 * Tests de compatibilité avec tous les types de réponses Laravel
 */
class ResponseTypesTest extends TestCase
{
    private TraceRecorder $traceRecorder;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('chronotrace.enabled', true);
        $this->traceRecorder = app(TraceRecorder::class);
    }

    public function test_finish_capture_with_json_response(): void
    {
        $request = Request::create('/test', 'GET');
        $traceId = $this->traceRecorder->startCapture($request);
        $response = new JsonResponse(['data' => 'test']);

        // Ne doit pas lever de TypeError
        $this->traceRecorder->finishCapture($traceId, $response, 0.1, 1024);

        $this->assertTrue(true);
    }

    public function test_finish_capture_with_redirect_response(): void
    {
        $request = Request::create('/test', 'GET');
        $traceId = $this->traceRecorder->startCapture($request);
        $response = new RedirectResponse('https://example.com');

        // Ne doit pas lever de TypeError
        $this->traceRecorder->finishCapture($traceId, $response, 0.1, 1024);

        $this->assertTrue(true);
    }

    public function test_finish_capture_with_regular_response(): void
    {
        $request = Request::create('/test', 'GET');
        $traceId = $this->traceRecorder->startCapture($request);
        $response = new Response('Hello World');

        // Ne doit pas lever de TypeError
        $this->traceRecorder->finishCapture($traceId, $response, 0.1, 1024);

        $this->assertTrue(true);
    }

    public function test_finish_capture_with_custom_response(): void
    {
        $request = Request::create('/test', 'GET');
        $traceId = $this->traceRecorder->startCapture($request);

        // Test avec une réponse custom qui hérite de Symfony\Component\HttpFoundation\Response
        $response = new class extends \Symfony\Component\HttpFoundation\Response
        {
            public function __construct()
            {
                parent::__construct('Custom Response', 200);
            }
        };

        // Ne doit pas lever de TypeError
        $this->traceRecorder->finishCapture($traceId, $response, 0.1, 1024);

        $this->assertTrue(true);
    }
}
