<?php

declare(strict_types=1);

it('can validate chronotrace mode configuration', function () {
    $validModes = ['always', 'sample', 'record_on_error'];
    $currentMode = config('chronotrace.mode');

    expect($currentMode)->toBeIn($validModes);
});

it('can validate sample rate is within bounds', function () {
    $sampleRate = config('chronotrace.sample_rate');

    expect($sampleRate)->toBeNumeric();
    expect($sampleRate)->toBeGreaterThanOrEqual(0);
    expect($sampleRate)->toBeLessThanOrEqual(1);
});

it('can validate storage configuration', function () {
    $storage = config('chronotrace.storage');
    $validStorageTypes = ['local', 's3'];

    expect($storage)->toBeString();
    expect($storage)->toBeIn($validStorageTypes);
});

it('can validate path configuration', function () {
    $path = config('chronotrace.path');

    expect($path)->toBeString();
    expect($path)->not()->toBeEmpty();
    expect($path)->toContain('chronotrace');
});

it('ensures scrub configuration is properly formatted', function () {
    $scrubFields = config('chronotrace.scrub');
    
    expect($scrubFields)->toBeArray();
    
    if (is_array($scrubFields)) {
        foreach ($scrubFields as $field) {
            expect($field)->toBeString();
            expect($field)->not()->toBeEmpty();
        }
    }
});it('can handle configuration in different environments', function () {
    // Dans l'environnement de test, chronotrace doit être activé
    expect(config('chronotrace.enabled'))->toBeTrue();

    // Le mode doit être valide
    expect(config('chronotrace.mode'))->toBeIn(['always', 'sample', 'record_on_error']);
});
