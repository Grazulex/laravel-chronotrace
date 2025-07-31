<?php

use Grazulex\LaravelChronotrace\Listeners\CacheEventListener;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Mockery;

it('captures cache hit events', function (): void {
    config(['chronotrace.capture.cache' => true]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldReceive('addCapturedData')
        ->once()
        ->with('cache', Mockery::on(fn ($data): bool => $data['type'] === 'hit'
            && isset($data['key'])
            && isset($data['value_size'])));

    $listener = new CacheEventListener($mockRecorder);

    // CacheHit($storeName, $key, $value, $tags)
    $event = new CacheHit('default', 'test_key', 'test_value', []);
    $listener->handleCacheHit($event);
});

it('captures cache miss events', function (): void {
    config(['chronotrace.capture.cache' => true]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldReceive('addCapturedData')
        ->once()
        ->with('cache', Mockery::on(fn ($data): bool => $data['type'] === 'miss'
            && isset($data['key'])));

    $listener = new CacheEventListener($mockRecorder);

    // CacheMissed($storeName, $key, $tags)
    $event = new CacheMissed('default', 'missing_key', []);
    $listener->handleCacheMissed($event);
});
it('scrubs sensitive cache keys', function (): void {
    config(['chronotrace.capture.cache' => true]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldReceive('addCapturedData')
        ->once()
        ->with('cache', Mockery::on(fn ($data): bool => $data['type'] === 'hit'
            && $data['key'] === '[SCRUBBED_CACHE_KEY]'  // La clé devrait être scrubbed
            && isset($data['value_size'])));

    $listener = new CacheEventListener($mockRecorder);

    // CacheHit($storeName, $key, $value, $tags)
    $event = new CacheHit('default', 'user_123_token', 'secret_token_value', []);
    $listener->handleCacheHit($event);
});

it('does not capture cache events when disabled', function (): void {
    config(['chronotrace.capture.cache' => false]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldNotReceive('addCapturedData');

    $listener = new CacheEventListener($mockRecorder);

    // CacheHit($storeName, $key, $value, $tags)
    $event = new CacheHit('default', 'test_key', 'test_value', []);
    $listener->handleCacheHit($event);
});
