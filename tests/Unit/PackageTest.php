<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('package is correctly installed', function () {
    expect(class_exists(\Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider::class))->toBeTrue();
});

it('config file path is accessible', function () {
    $configPath = __DIR__.'/../../config/chronotrace.php';
    expect(file_exists($configPath))->toBeTrue();
    expect(is_readable($configPath))->toBeTrue();
});

it('application can boot without errors', function () {
    /** @var TestCase $this */
    // Test que l'application peut démarrer sans erreur
    expect($this->app)->toBeInstanceOf(\Illuminate\Foundation\Application::class);
    expect($this->app->isBooted())->toBeTrue();
});

it('has no conflicting service providers', function () {
    /** @var TestCase $this */
    $providers = $this->app->getLoadedProviders();
    
    // Vérifier qu'il n'y a qu'une instance de notre service provider
    $chronotraceProviders = array_filter($providers, function ($provider, $key) {
        return is_string($key) && str_contains($key, 'Chronotrace');
    }, ARRAY_FILTER_USE_BOTH);
    
    expect(count($chronotraceProviders))->toBe(1);
});
