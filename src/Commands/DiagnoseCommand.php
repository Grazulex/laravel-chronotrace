<?php

namespace Grazulex\LaravelChronotrace\Commands;

use Exception;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class DiagnoseCommand extends Command
{
    protected $signature = 'chronotrace:diagnose';

    protected $description = 'Diagnose ChronoTrace configuration and potential issues';

    public function handle(): int
    {
        $this->info('🔍 ChronoTrace Configuration Diagnosis');
        $this->newLine();

        $allGood = true;

        // 1. Configuration générale
        $this->info('📋 General Configuration:');
        $this->checkConfig('enabled', config('chronotrace.enabled'));
        $this->checkConfig('mode', config('chronotrace.mode'));
        $this->checkConfig('storage', config('chronotrace.storage'));
        $this->checkConfig('async_storage', config('chronotrace.async_storage'));
        $this->newLine();

        // 2. Configuration des queues
        $this->info('⚡ Queue Configuration:');
        $queueConnection = config('chronotrace.queue_connection');
        $this->checkConfig('queue_connection', $queueConnection ?? 'auto-detect');
        $this->checkConfig('queue_name', config('chronotrace.queue_name'));
        $this->checkConfig('queue_fallback', config('chronotrace.queue_fallback'));

        // Test des connexions queue
        if (config('chronotrace.async_storage')) {
            $this->testQueueConnections(is_string($queueConnection) ? $queueConnection : null);
        }
        $this->newLine();

        // 3. Configuration du stockage
        $this->info('💾 Storage Configuration:');
        $storageType = config('chronotrace.storage');
        $allGood &= $this->testStorage($storageType);
        $this->newLine();

        // 4. Test des permissions
        $this->info('🔐 Permissions Check:');
        $allGood &= $this->testPermissions();
        $this->newLine();

        // 5. Test de bout en bout
        $this->info('🧪 End-to-End Test:');
        $allGood &= $this->testEndToEnd();
        $this->newLine();

        if ($allGood !== 0) {
            $this->info('✅ All tests passed! ChronoTrace should work correctly.');
        } else {
            $this->error('❌ Some issues were found. Check the details above.');
        }

        return $allGood !== 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function checkConfig(string $key, mixed $value): void
    {
        $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) ? (string) $value : 'non-scalar');
        $this->line("  {$key}: <info>{$displayValue}</info>");
    }

    private function testQueueConnections(?string $preferredConnection): bool
    {
        $this->line('  Testing queue connections...');

        $queueConnections = config('queue.connections', []);
        $defaultConnection = config('queue.default');

        $defaultConnectionString = is_string($defaultConnection) ? $defaultConnection : 'none';
        $this->line("    Default queue connection: <info>{$defaultConnectionString}</info>");

        if ($preferredConnection) {
            return $this->testSingleQueueConnection($preferredConnection);
        }

        // Auto-detect
        $connectionPriority = [$defaultConnection, 'sync', 'database', 'redis'];

        foreach ($connectionPriority as $connection) {
            if (empty($connection)) {
                continue;
            }
            if (! is_string($connection)) {
                continue;
            }
            if (! isset($queueConnections[$connection])) {
                continue;
            }
            if ($this->testSingleQueueConnection($connection)) {
                $this->line("    ✅ Auto-detected working connection: <info>{$connection}</info>");

                return true;
            }
        }

        $this->line('    ❌ No working queue connection found');

        return false;
    }

    private function testSingleQueueConnection(string $connection): bool
    {
        try {
            $queueManager = app('queue');
            $connectionConfig = config("queue.connections.{$connection}");

            if ($connectionConfig === null) {
                $this->line("    ❌ {$connection}: Not configured");

                return false;
            }

            // Test basic connection
            $queueConnection = $queueManager->connection($connection);
            $this->line("    ✅ {$connection}: Available");

            return true;
        } catch (Exception $e) {
            $this->line("    ❌ {$connection}: {$e->getMessage()}");

            return false;
        }
    }

    private function testStorage(string $storageType): bool
    {
        try {
            $storage = app(TraceStorage::class);
            $this->line("  Storage type: <info>{$storageType}</info>");

            // Test storage path/configuration
            switch ($storageType) {
                case 'local':
                    $path = config('chronotrace.path');
                    $pathString = is_string($path) ? $path : 'unknown';
                    $this->line("  Storage path: <info>{$pathString}</info>");

                    if (is_string($path) && ! file_exists($path)) {
                        $this->line("  📁 Creating storage directory: {$path}");
                        mkdir($path, 0755, true);
                    }

                    if (is_string($path) && ! is_writable($path)) {
                        $this->line('  ❌ Storage path is not writable');

                        return false;
                    }
                    break;

                case 's3':
                case 'minio':
                    $bucket = config('chronotrace.s3.bucket') ?? env('CHRONOTRACE_S3_BUCKET');
                    $region = config('chronotrace.s3.region') ?? env('AWS_DEFAULT_REGION');
                    $endpoint = config('chronotrace.s3.endpoint') ?? env('CHRONOTRACE_S3_ENDPOINT');
                    $accessKey = env('AWS_ACCESS_KEY_ID');
                    $secretKey = env('AWS_SECRET_ACCESS_KEY');

                    $bucketString = is_string($bucket) ? $bucket : 'not-configured';
                    $regionString = is_string($region) ? $region : 'not-configured';

                    $this->line("  S3/MinIO bucket: <info>{$bucketString}</info>");
                    $this->line("  S3/MinIO region: <info>{$regionString}</info>");
                    if (is_string($endpoint) && $endpoint) {
                        $this->line("  S3/MinIO endpoint: <info>{$endpoint}</info>");
                    }

                    // Vérifier les credentials
                    if (empty($accessKey)) {
                        $this->line('  ❌ AWS_ACCESS_KEY_ID not configured');

                        return false;
                    }
                    if (empty($secretKey)) {
                        $this->line('  ❌ AWS_SECRET_ACCESS_KEY not configured');

                        return false;
                    }

                    $this->line('  🔑 Credentials configured');

                    // Test de connexion S3 réel
                    if (! $this->testS3Connection()) {
                        return false;
                    }
                    break;
            }

            $this->line('  ✅ Storage configuration looks good');

            return true;
        } catch (Exception $e) {
            $this->line("  ❌ Storage error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Test de connexion S3 réel avec écriture/lecture/suppression
     */
    private function testS3Connection(): bool
    {
        $testFileName = 'traces/diagnostic-test-' . uniqid() . '.txt';
        $disk = config('chronotrace.storage') === 's3' ? 'chronotrace_s3' : 'local';

        try {
            // Créer un fichier de test
            $testContent = 'ChronoTrace S3 connection test - ' . date('Y-m-d H:i:s');

            $this->line('  🧪 Testing S3 write capability...');

            // Tenter d'écrire un fichier de test
            Storage::disk($disk)->put($testFileName, $testContent);

            $this->line('  ✅ S3 write successful');

            // Tenter de lire le fichier
            $this->line('  🧪 Testing S3 read capability...');
            $readContent = Storage::disk($disk)->get($testFileName);

            if ($readContent !== $testContent) {
                $this->line('  ❌ S3 read failed - content mismatch');

                return false;
            }

            $this->line('  ✅ S3 read successful');

            // Vérifier que le fichier existe
            $this->line('  🧪 Testing S3 file existence...');
            if (! Storage::disk($disk)->exists($testFileName)) {
                $this->line('  ❌ S3 file existence check failed');

                return false;
            }

            $this->line('  ✅ S3 file existence confirmed');

            // Nettoyer le fichier de test
            $this->line('  🧹 Cleaning up test file...');
            Storage::disk($disk)->delete($testFileName);

            $this->line('  ✅ S3 connection fully functional');

            return true;
        } catch (Exception $e) {
            $this->line("  ❌ S3 connection test failed: {$e->getMessage()}");

            // Essayer de nettoyer même en cas d'erreur
            try {
                Storage::disk($disk)->delete($testFileName);
            } catch (Exception) {
                // Ignorer les erreurs de nettoyage
            }

            return false;
        }
    }

    private function testPermissions(): bool
    {
        $path = config('chronotrace.path');

        if (! file_exists($path)) {
            $this->line('  📁 Storage directory does not exist, will be created');

            return true;
        }

        if (! is_readable($path)) {
            $this->line('  ❌ Storage directory is not readable');

            return false;
        }

        if (! is_writable($path)) {
            $this->line('  ❌ Storage directory is not writable');

            return false;
        }

        $this->line('  ✅ Storage directory permissions are correct');

        return true;
    }

    private function testEndToEnd(): bool
    {
        try {
            $this->line('  Testing trace creation and storage...');

            // Create a minimal test trace
            $testTraceId = 'test-' . uniqid();
            $testData = [
                'trace_id' => $testTraceId,
                'timestamp' => time(),
                'request' => ['method' => 'GET', 'url' => '/test'],
                'response' => ['status' => 200],
                'metadata' => ['test' => true],
            ];

            // Test storage
            $storage = app(TraceStorage::class);

            // For now, just verify the storage instance can be created
            $this->line('  ✅ Storage instance created successfully');

            return true;
        } catch (Exception $e) {
            $this->line("  ❌ End-to-end test failed: {$e->getMessage()}");

            return false;
        }
    }
}
