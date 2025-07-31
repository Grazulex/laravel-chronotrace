<?php

declare(strict_types=1);

it('has a valid default configuration', function (): void {
    $configPath = __DIR__ . '/../../config/chronotrace.php';

    expect(file_exists($configPath))->toBeTrue();

    $config = include $configPath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys([
            'enabled',
            'mode',
            'sample_rate',
            'storage',
            'path',
            'retention_days',
            'scrub',
        ]);
});

it('has valid mode values', function (): void {
    $configPath = __DIR__ . '/../../config/chronotrace.php';
    /** @var array<string, mixed> $config */
    $config = include $configPath;

    expect($config['mode'])->toBeIn(['always', 'sample', 'record_on_error']);
});

it('has valid sample rate', function (): void {
    $configPath = __DIR__ . '/../../config/chronotrace.php';
    /** @var array<string, mixed> $config */
    $config = include $configPath;

    expect($config['sample_rate'])
        ->toBeFloat()
        ->toBeBetween(0, 1);
});

it('has valid retention days', function (): void {
    $configPath = __DIR__ . '/../../config/chronotrace.php';
    /** @var array<string, mixed> $config */
    $config = include $configPath;

    expect($config['retention_days'])
        ->toBeInt()
        ->toBeGreaterThan(0);
});

it('has valid scrub array', function (): void {
    $configPath = __DIR__ . '/../../config/chronotrace.php';
    /** @var array<string, mixed> $config */
    $config = include $configPath;

    expect($config['scrub'])
        ->toBeArray()
        ->and($config['scrub'])->toContain('password', 'token');
});
