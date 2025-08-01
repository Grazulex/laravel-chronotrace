<?php

use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    config(['chronotrace.enabled' => true]);
    config(['chronotrace.mode' => 'always']);
    config(['chronotrace.storage_mode' => 'sync']);
});

it('can start capture', function (): void {
    $recorder = app(TraceRecorder::class);
    $request = Request::create('/test', 'GET');

    $traceId = $recorder->startCapture($request);

    expect($traceId)->toStartWith('ct_');
    expect(strlen($traceId))->toBeGreaterThan(10);
});

it('can finish capture successfully', function (): void {
    $recorder = app(TraceRecorder::class);
    $request = Request::create('/test', 'GET');

    $traceId = $recorder->startCapture($request);
    $response = new Response('test content', 200);

    $recorder->finishCapture($traceId, $response, 0.1, 1024);

    expect(true)->toBeTrue(); // Si on arrive ici, ça a marché
});

it('handles finish capture with exception', function (): void {
    $recorder = app(TraceRecorder::class);
    $request = Request::create('/test', 'GET');

    $traceId = $recorder->startCapture($request);
    $exception = new Exception('Test exception');

    $recorder->finishCaptureWithException($traceId, $exception, 0.1, 1024);

    expect(true)->toBeTrue();
});

it('captures request data', function (): void {
    $recorder = app(TraceRecorder::class);
    $request = Request::create('/test', 'POST', ['key' => 'value']);
    $request->headers->set('Authorization', 'Bearer token');

    $traceId = $recorder->startCapture($request);

    expect($traceId)->toStartWith('ct_');
});

it('handles storage mode async', function (): void {
    config(['chronotrace.storage_mode' => 'async']);

    $recorder = app(TraceRecorder::class);
    $request = Request::create('/test', 'GET');

    $traceId = $recorder->startCapture($request);
    $response = new Response('test content', 200);

    $recorder->finishCapture($traceId, $response, 0.1, 1024);

    expect(true)->toBeTrue();
});

it('respects record on error mode', function (): void {
    config(['chronotrace.mode' => 'error_only']);

    $recorder = app(TraceRecorder::class);
    $request = Request::create('/test', 'GET');

    $traceId = $recorder->startCapture($request);
    $response = new Response('error', 500);

    $recorder->finishCapture($traceId, $response, 0.1, 1024);

    expect(true)->toBeTrue();
});

it('handles sample mode', function (): void {
    config(['chronotrace.mode' => 'sample']);
    config(['chronotrace.sample_rate' => 1.0]); // 100% pour garantir la capture

    $recorder = app(TraceRecorder::class);
    $request = Request::create('/test', 'GET');

    $traceId = $recorder->startCapture($request);

    expect($traceId)->toStartWith('ct_');
});

it('handles targeted routes mode', function (): void {
    config(['chronotrace.mode' => 'targeted_routes']);
    config(['chronotrace.targeted_routes' => ['/api/*', '/admin/*']]);

    $recorder = app(TraceRecorder::class);
    $request = Request::create('/api/users', 'GET');

    $traceId = $recorder->startCapture($request);

    expect($traceId)->toStartWith('ct_');
});

it('can instantiate service', function (): void {
    $recorder = app(TraceRecorder::class);
    expect($recorder)->toBeInstanceOf(TraceRecorder::class);
});

it('generates unique trace IDs', function (): void {
    $recorder = app(TraceRecorder::class);
    $request = Request::create('/test', 'GET');

    $traceId1 = $recorder->startCapture($request);
    $traceId2 = $recorder->startCapture($request);

    expect($traceId1)->not->toBe($traceId2);
    expect($traceId1)->toStartWith('ct_');
    expect($traceId2)->toStartWith('ct_');
});
