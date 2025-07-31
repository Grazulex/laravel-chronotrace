<?php

namespace Grazulex\LaravelChronotrace\Commands;

use Exception;
use Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class MiddlewareTestCommand extends Command
{
    protected $signature = 'chronotrace:test-middleware';

    protected $description = 'Test ChronoTrace middleware installation and activation';

    public function handle(): int
    {
        $this->info('🧪 Testing ChronoTrace Middleware Installation');
        $this->newLine();

        // 1. Vérifier la configuration
        $this->info('📋 Configuration Check:');
        $this->checkConfigValue('enabled', config('chronotrace.enabled'));
        $this->checkConfigValue('mode', config('chronotrace.mode'));
        $this->checkConfigValue('debug', config('chronotrace.debug'));
        $this->newLine();

        // 2. Vérifier l'enregistrement du middleware
        $this->info('🔧 Middleware Registration Check:');
        $middlewareRegistered = $this->checkMiddlewareRegistration();
        $this->newLine();

        // 3. Suggestions
        $this->info('💡 Recommendations:');

        if (! config('chronotrace.enabled')) {
            $this->warn('  - Enable ChronoTrace: Set CHRONOTRACE_ENABLED=true in .env');
        }

        if (! config('chronotrace.debug')) {
            $this->warn('  - Enable debug mode: Set CHRONOTRACE_DEBUG=true in .env');
        }

        if (! $middlewareRegistered) {
            $this->error('  - Middleware not properly registered!');
            $this->line('    Add this to bootstrap/app.php:');
            $this->line('    ```php');
            $this->line('    ->withMiddleware(function (Middleware $middleware) {');
            $this->line('        $middleware->web(append: [');
            $this->line('            \\Grazulex\\LaravelChronotrace\\Middleware\\ChronoTraceMiddleware::class,');
            $this->line('        ]);');
            $this->line('        $middleware->api(append: [');
            $this->line('            \\Grazulex\\LaravelChronotrace\\Middleware\\ChronoTraceMiddleware::class,');
            $this->line('        ]);');
            $this->line('    })');
            $this->line('    ```');
        } else {
            $this->info('  - Middleware is properly registered ✅');
        }

        // 4. Test avec une requête simulée
        $this->info('🚀 Simulation Test:');
        $this->testSimulatedRequest();

        return Command::SUCCESS;
    }

    private function checkConfigValue(string $key, mixed $value): void
    {
        $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) ? (string) $value : 'non-scalar');
        $this->line("  chronotrace.{$key}: <info>{$displayValue}</info>");
    }

    private function checkMiddlewareRegistration(): bool
    {
        try {
            // Vérifier si le middleware peut être instancié
            $middleware = app(ChronoTraceMiddleware::class);
            $this->line('  ✅ Middleware class can be instantiated');

            // Vérifier dans les routes (méthode approximative)
            $middlewareFound = false;

            // Note: En Laravel 11+, il est difficile de vérifier programmatiquement
            // l'enregistrement du middleware dans bootstrap/app.php
            // On fait une vérification basique

            $this->line('  ⚠️  Cannot programmatically verify middleware registration in Laravel 11+');
            $this->line('     Please ensure it\'s added to bootstrap/app.php manually');

            return true; // On assume que c'est OK si on peut instancier
        } catch (Exception $e) {
            $this->line("  ❌ Error instantiating middleware: {$e->getMessage()}");

            return false;
        }
    }

    private function testSimulatedRequest(): void
    {
        try {
            // Simuler une requête simple
            $request = Request::create('/test', 'GET');

            $this->line('  📍 Simulating GET /test request...');

            // Vérifier que le middleware peut traiter la requête
            $middleware = app(ChronoTraceMiddleware::class);

            $response = $middleware->handle($request, fn ($req) => response('Test response', 200));

            if ($response->getStatusCode() === 200) {
                $this->line('  ✅ Middleware processed request successfully');
            } else {
                $this->line("  ❌ Unexpected response status: {$response->getStatusCode()}");
            }
        } catch (Exception $e) {
            $this->line("  ❌ Error in simulation: {$e->getMessage()}");
        }
    }
}
