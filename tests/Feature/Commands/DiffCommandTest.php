<?php

use Grazulex\LaravelChronotrace\Models\TraceContext;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Models\TraceRequest;
use Grazulex\LaravelChronotrace\Models\TraceResponse;
use Grazulex\LaravelChronotrace\Services\TraceComparator;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Support\Facades\Storage;

function makeDiffTrace(string $id, int $status, float $duration, array $http = [], string $url = '/api/orders'): TraceData
{
    return new TraceData(
        traceId: $id,
        timestamp: now()->toISOString(),
        environment: 'testing',
        request: new TraceRequest('GET', $url, [], [], [], [], null, [], 'agent', '127.0.0.1', microtime(true)),
        response: new TraceResponse($status, [], '', $duration, 1024, microtime(true), null, []),
        context: new TraceContext('12.0', '8.3', [], [], '', '', [], [], []),
        http: $http,
    );
}

function httpResponse(string $url, int $status): array
{
    return ['type' => 'response_received', 'method' => 'GET', 'url' => $url, 'status' => $status];
}

beforeEach(function (): void {
    Storage::fake('local');
    config(['chronotrace.storage' => 'local']);
    $this->app->forgetInstance(TraceStorage::class);
});

it('compares two traces in the comparator', function (): void {
    $before = makeDiffTrace('a', 200, 50.0, [httpResponse('https://x.test/a', 200), httpResponse('https://x.test/b', 200)]);
    $after = makeDiffTrace('b', 500, 80.0, [httpResponse('https://x.test/b', 502), httpResponse('https://x.test/c', 200)]);

    $diff = (new TraceComparator)->compare($before, $after);

    expect($diff['has_differences'])->toBeTrue()
        ->and($diff['status'])->toMatchArray(['before' => 200, 'after' => 500, 'changed' => true])
        ->and($diff['duration']['delta'])->toBe(30.0)
        ->and($diff['http_added'])->toBe(['GET https://x.test/c'])
        ->and($diff['http_removed'])->toBe(['GET https://x.test/a'])
        ->and($diff['http_status_changes'])->toBe([['call' => 'GET https://x.test/b', 'before' => 200, 'after' => 502]]);
});

it('honours the duration threshold', function (): void {
    $diff = (new TraceComparator)->compare(makeDiffTrace('a', 200, 50.0), makeDiffTrace('b', 200, 55.0), 10.0);

    expect($diff['has_differences'])->toBeFalse();
});

it('reports differences from the command', function (): void {
    $storage = app(TraceStorage::class);
    $storage->store(makeDiffTrace('ct_diff_1', 200, 50.0, [httpResponse('https://x.test/a', 200)]));
    $storage->store(makeDiffTrace('ct_diff_2', 500, 90.0));

    $this->artisan('chronotrace:diff', ['id1' => 'ct_diff_1', 'id2' => 'ct_diff_2'])
        ->expectsOutputToContain('Status: 200 → 500')
        ->expectsOutputToContain('- HTTP call removed: GET https://x.test/a')
        ->assertExitCode(0);
});

it('reports no differences for identical traces', function (): void {
    $storage = app(TraceStorage::class);
    $storage->store(makeDiffTrace('ct_same_1', 200, 50.0));
    $storage->store(makeDiffTrace('ct_same_2', 200, 50.0));

    $this->artisan('chronotrace:diff', ['id1' => 'ct_same_1', 'id2' => 'ct_same_2'])
        ->expectsOutput('No differences found.')
        ->assertExitCode(0);
});

it('fails when a trace does not exist', function (): void {
    app(TraceStorage::class)->store(makeDiffTrace('ct_exists', 200, 50.0));

    $this->artisan('chronotrace:diff', ['id1' => 'ct_exists', 'id2' => 'ct_missing'])
        ->expectsOutput('Trace not found: ct_missing')
        ->assertExitCode(1);
});
