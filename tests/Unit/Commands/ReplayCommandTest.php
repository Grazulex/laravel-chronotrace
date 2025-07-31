<?php

use Grazulex\LaravelChronotrace\Models\TraceContext;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Models\TraceRequest;
use Grazulex\LaravelChronotrace\Models\TraceResponse;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Mockery;

it('displays captured events from trace data', function (): void {
    $request = new TraceRequest(
        method: 'POST',
        url: 'https://api.example.com/users',
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
        status: 201,
        headers: [],
        content: '{"id":1}',
        duration: 250.5,
        memoryUsage: 2048000,
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

    // Créer des événements de test capturés par nos listeners
    $databaseEvents = [
        [
            'type' => 'query',
            'sql' => 'INSERT INTO users (name, email) VALUES (?, ?)',
            'bindings' => ['John Doe', 'john@example.com'],
            'time' => 15.2,
            'connection' => 'mysql',
            'timestamp' => microtime(true),
        ],
        [
            'type' => 'transaction_begin',
            'connection' => 'mysql',
            'timestamp' => microtime(true),
        ],
    ];

    $cacheEvents = [
        [
            'type' => 'hit',
            'key' => 'user_cache_key',
            'value_size' => 256,
            'store' => 'redis',
            'timestamp' => microtime(true),
        ],
    ];

    $httpEvents = [
        [
            'type' => 'request_sending',
            'method' => 'GET',
            'url' => 'https://external-api.com/data',
            'body_size' => 0,
            'timestamp' => microtime(true),
        ],
        [
            'type' => 'response_received',
            'method' => 'GET',
            'url' => 'https://external-api.com/data',
            'status' => 200,
            'response_size' => 1024,
            'timestamp' => microtime(true),
        ],
    ];

    $jobEvents = [
        [
            'type' => 'job_processing',
            'job_name' => 'App\\Jobs\\SendWelcomeEmail',
            'queue' => 'emails',
            'connection' => 'redis',
            'attempts' => 1,
            'timestamp' => microtime(true),
        ],
    ];

    $traceData = new TraceData(
        traceId: 'test-trace-123',
        timestamp: '2024-01-01 12:00:00',
        environment: 'testing',
        request: $request,
        response: $response,
        context: $context,
        database: $databaseEvents,
        cache: $cacheEvents,
        http: $httpEvents,
        jobs: $jobEvents,
    );

    $mockStorage = Mockery::mock(TraceStorage::class);
    $mockStorage->shouldReceive('retrieve')
        ->with('test-trace-123')
        ->andReturn($traceData);

    $this->app->instance(TraceStorage::class, $mockStorage);

    $this->artisan('chronotrace:replay', ['trace-id' => 'test-trace-123'])
        ->expectsOutput('Replaying trace test-trace-123...')
        ->expectsOutput('=== TRACE INFORMATION ===')
        ->expectsOutput('=== CAPTURED EVENTS ===')
        ->assertExitCode(0);
});

it('handles trace not found gracefully', function (): void {
    $mockStorage = Mockery::mock(TraceStorage::class);
    $mockStorage->shouldReceive('retrieve')
        ->with('non-existent-trace')
        ->andReturn(null);

    $this->app->instance(TraceStorage::class, $mockStorage);

    $this->artisan('chronotrace:replay', ['trace-id' => 'non-existent-trace'])
        ->expectsOutput('Replaying trace non-existent-trace...')
        ->expectsOutput('Trace non-existent-trace not found.')
        ->assertExitCode(1);
});

it('respects filter options for event types', function (): void {
    $traceData = createMockTraceDataWithEvents();

    $mockStorage = Mockery::mock(TraceStorage::class);
    $mockStorage->shouldReceive('retrieve')
        ->with('test-trace')
        ->andReturn($traceData);

    $this->app->instance(TraceStorage::class, $mockStorage);

    // Test --db option
    $this->artisan('chronotrace:replay', ['trace-id' => 'test-trace', '--db' => true])
        ->expectsOutput('📊 DATABASE EVENTS')
        ->doesntExpectOutput('🗄️  CACHE EVENTS')
        ->doesntExpectOutput('🌐 HTTP EVENTS')
        ->doesntExpectOutput('⚙️  JOB EVENTS')
        ->assertExitCode(0);
});

function createMockTraceDataWithEvents(): TraceData
{
    $request = new TraceRequest('GET', '/', [], [], [], [], null, [], '', '', microtime(true));
    $response = new TraceResponse(200, [], '', 0, 0, microtime(true), null, []);
    $context = new TraceContext('11.0', '8.3', [], [], '', '', [], [], []);

    return new TraceData(
        traceId: 'test-trace',
        timestamp: '2024-01-01',
        environment: 'test',
        request: $request,
        response: $response,
        context: $context,
        database: [['type' => 'query', 'sql' => 'SELECT 1', 'timestamp' => microtime(true)]],
        cache: [['type' => 'hit', 'key' => 'test', 'timestamp' => microtime(true)]],
        http: [['type' => 'request_sending', 'url' => 'test', 'timestamp' => microtime(true)]],
        jobs: [['type' => 'job_processing', 'job_name' => 'TestJob', 'timestamp' => microtime(true)]],
    );
}
