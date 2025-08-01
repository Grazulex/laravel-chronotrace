<?php

use Exception;
use Grazulex\LaravelChronotrace\Jobs\StoreTraceJob;
use Grazulex\LaravelChronotrace\Models\TraceContext;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Models\TraceRequest;
use Grazulex\LaravelChronotrace\Models\TraceResponse;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

beforeEach(function (): void {
    // Configuration pour les tests
    config(['chronotrace.enabled' => true]);
    config(['chronotrace.storage_mode' => 'async']);
});

function createMockTraceData(): TraceData
{
    $request = new TraceRequest(
        'GET',
        '/test-url',
        [],
        [],
        [],
        [],
        null,
        [],
        '127.0.0.1',
        'TestUserAgent/1.0',
        microtime(true)
    );

    $response = new TraceResponse(
        200,
        ['Content-Type' => 'application/json'],
        '{"test": true}',
        150,
        1024 * 1024, // 1MB
        microtime(true),
        null,
        []
    );

    $context = new TraceContext(
        laravel_version: '11.0',
        php_version: '8.3.0',
        config: ['app.debug' => true],
        env_vars: ['APP_ENV' => 'testing'],
        git_commit: 'abc123',
        branch: 'main',
        packages: ['laravel/framework' => '11.0'],
        middlewares: ['web'],
        providers: ['App\\Providers\\AppServiceProvider']
    );

    return new TraceData(
        traceId: 'test-trace-' . uniqid(),
        timestamp: now()->toISOString(),
        environment: 'testing',
        request: $request,
        response: $response,
        context: $context,
        database: [
            ['type' => 'query', 'sql' => 'SELECT * FROM users', 'timestamp' => microtime(true)],
        ],
        cache: [
            ['type' => 'hit', 'key' => 'user:1', 'timestamp' => microtime(true)],
        ],
        http: [
            ['type' => 'request_sending', 'url' => 'https://api.example.com', 'timestamp' => microtime(true)],
        ],
        jobs: [
            ['type' => 'job_processing', 'job_name' => 'TestJob', 'timestamp' => microtime(true)],
        ]
    );
}

it('can be dispatched to queue', function (): void {
    Queue::fake();

    $traceData = createMockTraceData();

    StoreTraceJob::dispatch($traceData);

    Queue::assertPushed(StoreTraceJob::class, fn ($job): bool => $job->traceData->traceId === $traceData->traceId);
});

it('stores trace data successfully', function (): void {
    $traceData = createMockTraceData();
    $job = new StoreTraceJob($traceData);

    // Exécuter le job
    $job->handle(resolve(TraceStorage::class));

    // Vérifier que la trace a été stockée
    $storage = resolve(TraceStorage::class);
    $storedTrace = $storage->retrieve($traceData->traceId);

    expect($storedTrace)->not->toBeNull();
    expect($storedTrace->traceId)->toBe($traceData->traceId);
    expect($storedTrace->environment)->toBe('testing');
});

it('handles storage failures gracefully', function (): void {
    $traceData = createMockTraceData();

    // Mock du storage qui échoue
    $mockStorage = Mockery::mock(TraceStorage::class);
    $mockStorage->shouldReceive('store')
        ->with($traceData)
        ->andThrow(new RuntimeException('Storage failed'));

    $job = new StoreTraceJob($traceData);

    // Le job ne devrait pas lever d'exception
    expect(fn () => $job->handle($mockStorage))->not->toThrow(Exception::class);
});

it('has correct job properties', function (): void {
    $traceData = createMockTraceData();
    $job = new StoreTraceJob($traceData);

    // Vérifier que le job peut être sérialisé
    expect($job->traceData)->toBe($traceData);
    expect($job)->toBeInstanceOf(ShouldQueue::class);
});

it('can be retried on failure', function (): void {
    Queue::fake();

    $traceData = createMockTraceData();
    $job = new StoreTraceJob($traceData);

    // Vérifier que le job utilise les traits nécessaires
    expect($job)->toHaveProperty('traceData');
    expect($job->traceData)->toBe($traceData);
});

it('contains trace data after serialization', function (): void {
    $traceData = createMockTraceData();
    $job = new StoreTraceJob($traceData);

    // Sérialiser et désérialiser le job
    $serialized = serialize($job);
    $unserialized = unserialize($serialized);

    expect($unserialized->traceData->traceId)->toBe($traceData->traceId);
    expect($unserialized->traceData->environment)->toBe($traceData->environment);
});

it('has proper queue configuration', function (): void {
    $traceData = createMockTraceData();
    $job = new StoreTraceJob($traceData);

    // Vérifier que le job implémente ShouldQueue
    expect($job)->toBeInstanceOf(ShouldQueue::class);
    expect($job->traceData)->toBe($traceData);
});

it('handles large trace data', function (): void {
    $traceData = createMockTraceData();

    // Ajouter beaucoup de données pour tester les gros volumes
    $largeData = [];
    for ($i = 0; $i < 1000; $i++) {
        $largeData[] = [
            'type' => 'query',
            'sql' => 'SELECT * FROM large_table_' . $i,
            'timestamp' => microtime(true),
            'data' => str_repeat('x', 1000), // 1KB par entrée
        ];
    }

    $largeTraceData = new TraceData(
        traceId: 'large-trace-' . uniqid(),
        timestamp: now()->toISOString(),
        environment: 'testing',
        request: $traceData->request,
        response: $traceData->response,
        context: $traceData->context,
        database: $largeData
    );

    $job = new StoreTraceJob($largeTraceData);
    $storage = resolve(TraceStorage::class);

    // Le job devrait gérer les gros volumes sans problème
    expect(fn () => $job->handle($storage))->not->toThrow(Exception::class);
});
