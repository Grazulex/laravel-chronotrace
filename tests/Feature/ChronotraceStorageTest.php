<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('can create chronotrace storage path', function (): void {
    $storagePath = storage_path('chronotrace');

    // Créer le dossier si il n'existe pas
    if (! File::exists($storagePath)) {
        File::makeDirectory($storagePath, 0755, true);
    }

    expect(File::exists($storagePath))->toBeTrue();
    expect(File::isDirectory($storagePath))->toBeTrue();
});

it('can write to chronotrace log file', function (): void {
    $testLogFile = storage_path('chronotrace/test.log');
    $testContent = 'Test chronotrace entry: ' . now()->toISOString();

    // S'assurer que le dossier existe
    $storagePath = storage_path('chronotrace');
    if (! File::exists($storagePath)) {
        File::makeDirectory($storagePath, 0755, true);
    }

    File::put($testLogFile, $testContent);

    expect(File::exists($testLogFile))->toBeTrue();
    expect(File::get($testLogFile))->toBe($testContent);

    // Nettoyer après le test
    File::delete($testLogFile);
});

it('respects scrub configuration', function (): void {
    $scrubFields = config('chronotrace.scrub');

    expect($scrubFields)->toBeArray();
    expect($scrubFields)->toContain('password');
    expect($scrubFields)->toContain('token');
});

it('has valid retention policy', function (): void {
    $retentionDays = config('chronotrace.retention_days');

    expect($retentionDays)->toBeInt();
    expect($retentionDays)->toBeGreaterThan(0);
    expect($retentionDays)->toBeLessThanOrEqual(365); // Maximum 1 year seems reasonable
});
