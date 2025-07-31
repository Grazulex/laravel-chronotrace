<?php

use Grazulex\LaravelChronotrace\Listeners\HttpEventListener;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Mockery;

it('captures HTTP request events', function (): void {
    config(['chronotrace.capture.http' => true]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldReceive('addCapturedData')
        ->once()
        ->with('http', Mockery::on(fn ($data): bool => $data['type'] === 'request_sending'
            && isset($data['url'])
            && isset($data['method'])));

    $listener = new HttpEventListener($mockRecorder);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('url')->andReturn('https://api.example.com/users');
    $mockRequest->shouldReceive('method')->andReturn('GET');
    $mockRequest->shouldReceive('headers')->andReturn([]);
    $mockRequest->shouldReceive('body')->andReturn('');

    $event = new RequestSending($mockRequest);
    $listener->handleRequestSending($event);
});

it('captures HTTP response events', function (): void {
    config(['chronotrace.capture.http' => true]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldReceive('addCapturedData')
        ->once()
        ->with('http', Mockery::on(fn ($data): bool => $data['type'] === 'response_received'
            && isset($data['status'])
            && isset($data['url'])));

    $listener = new HttpEventListener($mockRecorder);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('url')->andReturn('https://api.example.com/users');
    $mockRequest->shouldReceive('method')->andReturn('GET');

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('status')->andReturn(200);
    $mockResponse->shouldReceive('transferStats')->andReturn((object) ['total_time' => 0.5]);
    $mockResponse->shouldReceive('body')->andReturn('{"status":"ok"}');
    $mockResponse->shouldReceive('headers')->andReturn([]);

    $event = new ResponseReceived($mockRequest, $mockResponse);
    $listener->handleResponseReceived($event);
});

it('does not capture HTTP events when disabled', function (): void {
    config(['chronotrace.capture.http' => false]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldNotReceive('addCapturedData');

    $listener = new HttpEventListener($mockRecorder);

    $mockRequest = Mockery::mock(Request::class);
    $mockRequest->shouldReceive('url')->andReturn('https://api.example.com/users');
    $mockRequest->shouldReceive('method')->andReturn('GET');
    $mockRequest->shouldReceive('headers')->andReturn([]);
    $mockRequest->shouldReceive('body')->andReturn('');

    $event = new RequestSending($mockRequest);
    $listener->handleRequestSending($event);
});
