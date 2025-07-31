<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class);

it('can publish config file', function () {
    // Vérifier que la commande de publication existe
    $commands = Artisan::all();
    
    expect($commands)->toHaveKey('vendor:publish');
});

it('loads chronotrace config in laravel app', function () {
    // Vérifier que la config est chargée
    expect(config('chronotrace'))->not()->toBeNull();
    expect(config('chronotrace'))->toBeArray();
});

it('has chronotrace enabled in test environment', function () {
    // Vérifier que chronotrace est activé pour les tests
    expect(config('chronotrace.enabled'))->toBeTrue();
});

it('can access chronotrace config values', function () {
    expect(config('chronotrace.mode'))->toBe('record_on_error');
    expect(config('chronotrace.storage'))->toBe('local');
    expect(config('chronotrace.retention_days'))->toBe(15);
});

it('has correct application environment setup', function () {
    /** @var TestCase $this */
    expect($this->app->environment())->toBe('testing');
    expect(config('database.default'))->toBe('testing');
});