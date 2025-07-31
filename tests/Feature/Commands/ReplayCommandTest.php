<?php

use Grazulex\LaravelChronotrace\Commands\ReplayCommand;
use Grazulex\LaravelChronotrace\Models\TraceContext;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Models\TraceRequest;
use Grazulex\LaravelChronotrace\Models\TraceResponse;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;

it('can replay an existing trace', function (): void {
    $request = new TraceRequest(
        method: 'GET',
        url: 'https://example.com/test',
        headers: [],
        query: [],
        input: [],
        files: [],
        user: null,
        session: [],
        userAgent: 'Test Agent',
        ip: '127.0.0.1',
        timestamp: microtime(true),
    );

    $response = new TraceResponse(
        status: 200,
        headers: [],
        content: 'OK',
        duration: 0.5,
        memoryUsage: 1024,
        timestamp: microtime(true),
        exception: null,
        cookies: [],
    );

    $context = new TraceContext(
        laravel_version: '11.0',
        php_version: '8.3',
        config: [],
        env_vars: [],
        git_commit: 'abc123',
        branch: 'main',
        packages: [],
        middlewares: [],
        providers: [],
    );

    $traceData = new TraceData(
        traceId: 'abc123',
        timestamp: '2024-01-01 12:00:00',
        environment: 'testing',
        request: $request,
        response: $response,
        context: $context,
        database: [],
        cache: [],
        http: [],
        mail: [],
        notifications: [],
        events: [],
        jobs: [],
        filesystem: [],
        metadata: [],
    );

    $storage = Mockery::mock(TraceStorage::class);
    $storage->shouldReceive('retrieve')
        ->with('abc123')
        ->once()
        ->andReturn($traceData);

    $this->instance(TraceStorage::class, $storage);

    $this->artisan(ReplayCommand::class, ['trace-id' => 'abc123'])
        ->expectsOutput('Replaying trace abc123...')
        ->expectsOutput('=== TRACE INFORMATION ===')
        ->expectsOutput('🆔 Trace ID: abc123')
        ->expectsOutput('🌍 Environment: testing')
        ->expectsOutput('🔗 Request URL: https://example.com/test')
        ->expectsOutput('📊 Response Status: 200')
        ->expectsOutput('=== CAPTURED EVENTS ===')
        ->assertExitCode(0);
});

it('handles missing trace gracefully', function (): void {
    $storage = Mockery::mock(TraceStorage::class);
    $storage->shouldReceive('retrieve')
        ->with('missing')
        ->once()
        ->andReturn(null);

    $this->instance(TraceStorage::class, $storage);

    $this->artisan(ReplayCommand::class, ['trace-id' => 'missing'])
        ->expectsOutput('Trace missing not found.')
        ->assertExitCode(1);
});

it('handles storage errors gracefully', function (): void {
    $storage = Mockery::mock(TraceStorage::class);
    $storage->shouldReceive('retrieve')
        ->once()
        ->andThrow(new Exception('Storage error'));

    $this->instance(TraceStorage::class, $storage);

    $this->artisan(ReplayCommand::class, ['trace-id' => 'abc123'])
        ->expectsOutput('Failed to replay trace: Storage error')
        ->assertExitCode(1);
});
