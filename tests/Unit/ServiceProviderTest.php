<?php

declare(strict_types=1);

use Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider;

it('can be instantiated', function (): void {
    /** @var Tests\TestCase $this */
    $provider = new LaravelChronotraceServiceProvider($this->getApp());

    expect($provider)->toBeInstanceOf(LaravelChronotraceServiceProvider::class);
});

it('registers the service provider correctly', function (): void {
    /** @var Tests\TestCase $this */
    $providers = $this->getApp()->getLoadedProviders();

    expect($providers)->toHaveKey(LaravelChronotraceServiceProvider::class);
});
it('merges config correctly', function (): void {
    // Le config devrait être disponible
    expect(config('chronotrace'))->toBeArray();
    expect(config('chronotrace.enabled'))->toBeTrue();
    expect(config('chronotrace.mode'))->toBe('record_on_error');
    expect(config('chronotrace.sample_rate'))->toBe(0.001);
    expect(config('chronotrace.storage'))->toBe('local');
    expect(config('chronotrace.retention_days'))->toBe(15);
});

it('has correct config structure', function (): void {
    $config = config('chronotrace');

    expect($config)->toHaveKeys([
        'enabled',
        'mode',
        'sample_rate',
        'storage',
        'path',
        'retention_days',
        'scrub',
    ]);
});

it('has valid config values', function (): void {
    expect(config('chronotrace.mode'))->toBeIn(['always', 'sample', 'record_on_error']);
    expect(config('chronotrace.sample_rate'))->toBeFloat();
    expect(config('chronotrace.storage'))->toBeString();
    expect(config('chronotrace.retention_days'))->toBeInt();
    expect(config('chronotrace.scrub'))->toBeArray();
});
