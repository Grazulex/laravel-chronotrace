<?php

declare(strict_types=1);

use Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider;
use Illuminate\Foundation\Application;

it('package is correctly installed', function (): void {
    expect(class_exists(LaravelChronotraceServiceProvider::class))->toBeTrue();
});

it('config file path is accessible', function (): void {
    $configPath = __DIR__ . '/../../config/chronotrace.php';
    expect(file_exists($configPath))->toBeTrue();
    expect(is_readable($configPath))->toBeTrue();
});

it('application can boot without errors', function (): void {
    /** @var Tests\TestCase $this */
    // Test que l'application peut démarrer sans erreur
    $app = $this->getApp();
    expect($app)->toBeInstanceOf(Application::class);
    expect($app->isBooted())->toBeTrue();
});

it('has no conflicting service providers', function (): void {
    /** @var Tests\TestCase $this */
    $providers = $this->getApp()->getLoadedProviders();

    // Vérifier qu'il n'y a qu'une instance de notre service provider
    $chronotraceProviders = array_filter($providers, fn($provider, $key): bool => str_contains((string) $key, 'Chronotrace'), ARRAY_FILTER_USE_BOTH);

    expect(count($chronotraceProviders))->toBe(1);
});
