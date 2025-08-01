<?php

namespace Grazulex\LaravelChronotrace\Commands;

use Exception;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TestInternalCommand extends Command
{
    protected $signature = 'chronotrace:test-internal {--with-db} {--with-cache} {--with-events}';

    protected $description = 'Test ChronoTrace with internal Laravel operations (DB, Cache, etc.)';

    public function handle(TraceRecorder $recorder): int
    {
        if (! config('chronotrace.enabled')) {
            $this->warn('⚠️  ChronoTrace is disabled. Enable it in config to see traces.');

            return Command::FAILURE;
        }

        $this->info('🧪 Testing ChronoTrace with internal Laravel operations...');

        // Start tracing and force storage for testing
        $originalMode = config('chronotrace.mode');
        config(['chronotrace.mode' => 'always']);

        // Créer une fausse requête pour la trace
        $request = Request::create('/chronotrace-test', 'GET', [
            'test' => 'internal-operations',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        $traceId = $recorder->startCapture($request);
        $this->line("📎 Starting trace: {$traceId}");

        $operations = [];

        // Test des opérations de base de données
        if ($this->option('with-db') || ! $this->hasAnyOption()) {
            $this->info('🗄️  Testing database operations...');
            try {
                // Effectuer quelques requêtes DB pour générer des événements
                $start = microtime(true);

                // Test de requête simple compatible avec tous les drivers
                $result = DB::select('SELECT 1 as test_value');

                // Test avec une requête plus complexe si possible
                try {
                    // Utiliser une requête qui marche sur SQLite (par défaut dans testbench)
                    $count = DB::table('sqlite_master')->where('type', 'table')->count();
                } catch (Exception $e) {
                    // Fallback pour autres drivers
                    $result = DB::select('SELECT COUNT(*) as count FROM (SELECT 1 as dummy) as t');
                }

                $duration = (microtime(true) - $start) * 1000;
                $operations[] = 'Database queries executed (' . number_format($duration, 2) . 'ms)';
            } catch (Exception $e) {
                $this->warn("Database test failed: {$e->getMessage()}");
                $operations[] = "Database test failed (but that's ok in package context)";
            }
        }

        // Test des opérations de cache
        if ($this->option('with-cache') || ! $this->hasAnyOption()) {
            $this->info('💾 Testing cache operations...');
            try {
                $start = microtime(true);

                // Opérations de cache pour générer des événements
                $testKey = 'chronotrace_test_' . uniqid();
                $testValue = ['test' => 'data', 'timestamp' => date('Y-m-d H:i:s')];

                Cache::put($testKey, $testValue, 60);
                $retrieved = Cache::get($testKey);
                Cache::forget($testKey);

                $duration = (microtime(true) - $start) * 1000;
                $operations[] = 'Cache operations executed (' . number_format($duration, 2) . 'ms)';
            } catch (Exception $e) {
                $this->warn("Cache test failed: {$e->getMessage()}");
                $operations[] = "Cache test failed (but that's ok in package context)";
            }
        }

        // Test d'événements personnalisés
        if ($this->option('with-events') || ! $this->hasAnyOption()) {
            $this->info('📡 Testing custom events...');
            try {
                $start = microtime(true);

                // Simuler des événements personnalisés (compatible avec testbench)
                if (function_exists('event')) {
                    event('chronotrace.test.started', ['trace_id' => $traceId]);
                    event('chronotrace.test.processing', ['operations' => count($operations)]);
                    event('chronotrace.test.completed', ['success' => true]);
                } else {
                    // Fallback si event() n'est pas disponible
                    $this->line('Events simulated (function not available in this context)');
                }

                $duration = (microtime(true) - $start) * 1000;
                $operations[] = 'Custom events fired (' . number_format($duration, 2) . 'ms)';
            } catch (Exception $e) {
                $this->warn("Events test failed: {$e->getMessage()}");
                $operations[] = "Events test failed (but that's ok in package context)";
            }
        }

        // Simuler quelques opérations additionnelles
        $this->info('⚙️  Performing additional operations...');
        // Simuler du travail et finaliser
        sleep(1);

        // Créer une réponse de test et finaliser la capture
        $responseData = [
            'test' => 'internal-operations',
            'trace_id' => $traceId,
            'operations_performed' => $operations,
            'timestamp' => date('Y-m-d H:i:s'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'context' => 'Laravel Package with Orchestra Testbench',
        ];

        $jsonContent = json_encode($responseData, JSON_PRETTY_PRINT);
        if ($jsonContent === false) {
            $jsonContent = '{"error": "Failed to encode response data"}';
        }

        $response = new Response(
            $jsonContent,
            200,
            ['Content-Type' => 'application/json']
        );

        $duration = 1.0; // 1 seconde de simulation
        $memoryUsage = memory_get_usage(true);

        // Finish capture and restore original configuration
        $recorder->finishCapture($traceId, $response, $duration, $memoryUsage);
        config(['chronotrace.mode' => $originalMode]);

        $this->info('✅ Internal operations test completed!');
        $this->line("📊 Trace ID: {$traceId}");
        $this->line('💡 Use: php artisan chronotrace:replay ' . $traceId . ' to view the captured trace');
        $this->line('🧪 Use: php artisan chronotrace:replay ' . $traceId . ' --generate-test to create a test file');

        return Command::SUCCESS;
    }

    private function hasAnyOption(): bool
    {
        if ($this->option('with-db')) {
            return true;
        }
        if ($this->option('with-cache')) {
            return true;
        }

        return (bool) $this->option('with-events');
    }
}
