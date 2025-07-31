<?php

declare(strict_types=1);

namespace Tests\Integration;

use Override;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class S3StorageTest extends TestCase
{
    use RefreshDatabase;

    private TraceStorage $storage;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Configuration S3 de test
        Config::set('chronotrace.storage', 's3');
        Config::set('chronotrace.s3', [
            'bucket' => 'test-chronotrace',
            'region' => 'us-east-1',
            'endpoint' => null,
            'path_prefix' => 'test-traces',
        ]);

        // Mock le disk S3 pour les tests
        Config::set('filesystems.disks.chronotrace_s3', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/s3'),
            'visibility' => 'private',
        ]);

        $this->storage = new TraceStorage('chronotrace_s3', true);
    }

    public function test_s3_storage_configuration_is_correct(): void
    {
        // Vérifier que la configuration S3 est bien chargée
        $this->assertSame('s3', config('chronotrace.storage'));
        $this->assertSame('test-chronotrace', config('chronotrace.s3.bucket'));
        $this->assertSame('test-traces', config('chronotrace.s3.path_prefix'));
    }

    public function test_can_store_trace_with_s3_storage(): void
    {
        $trace = TraceData::fromArray([
            'trace_id' => 'test-s3-trace-' . time(),
            'timestamp' => now()->toISOString(),
            'environment' => 'testing',
            'request' => [
                'method' => 'GET',
                'url' => '/test-s3',
                'headers' => ['User-Agent' => 'Test'],
            ],
            'response' => [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => ['message' => 'S3 test successful'],
            ],
        ]);

        $bundlePath = $this->storage->store($trace);

        $this->assertNotEmpty($bundlePath);
        $this->assertStringContainsString(date('Y-m-d'), $bundlePath);
        $this->assertStringEndsWith('.zip', $bundlePath);
    }

    public function test_can_list_traces_from_s3_storage(): void
    {
        // Créer quelques traces de test
        $traces = [];
        for ($i = 0; $i < 3; $i++) {
            $trace = TraceData::fromArray([
                'trace_id' => 'test-s3-list-' . $i . '-' . time(),
                'timestamp' => now()->toISOString(),
                'environment' => 'testing',
                'request' => ['method' => 'GET', 'url' => "/test-list-{$i}"],
                'response' => ['status' => 200],
            ]);

            $traces[] = $this->storage->store($trace);
        }

        $listed = $this->storage->list();

        $this->assertGreaterThanOrEqual(3, count($listed));

        foreach ($traces as $bundlePath) {
            $found = false;
            foreach ($listed as $listedTrace) {
                if (str_contains((string) $listedTrace['path'], basename($bundlePath, '.zip'))) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Trace {$bundlePath} should be in the list");
        }
    }

    public function test_s3_storage_handles_compression(): void
    {
        $trace = TraceData::fromArray([
            'trace_id' => 'test-s3-compression-' . time(),
            'timestamp' => now()->toISOString(),
            'environment' => 'testing',
            'request' => [
                'method' => 'POST',
                'url' => '/test-compression',
                'body' => str_repeat('Large payload for compression test. ', 1000),
            ],
            'response' => [
                'status' => 200,
                'body' => str_repeat('Large response for compression test. ', 1000),
            ],
        ]);

        $bundlePath = $this->storage->store($trace);

        $this->assertNotEmpty($bundlePath);

        // Vérifier que le fichier ZIP existe et n'est pas vide
        $fullPath = Storage::disk('chronotrace_s3')->path($bundlePath);
        $this->assertFileExists($fullPath);
        $this->assertGreaterThan(0, filesize($fullPath));
    }

    public function test_s3_storage_retrieves_trace_correctly(): void
    {
        $originalTrace = TraceData::fromArray([
            'trace_id' => 'test-s3-retrieve-' . time(),
            'timestamp' => now()->toISOString(),
            'environment' => 'testing',
            'request' => [
                'method' => 'PUT',
                'url' => '/test-retrieve',
                'headers' => ['Authorization' => 'Bearer token123'],
            ],
            'response' => [
                'status' => 201,
                'body' => ['id' => 123, 'created' => true],
            ],
        ]);

        $bundlePath = $this->storage->store($originalTrace);
        $retrievedTrace = $this->storage->retrieve($bundlePath);

        $this->assertNotNull($retrievedTrace);
        $this->assertSame($originalTrace->traceId, $retrievedTrace->traceId);
        $this->assertSame($originalTrace->request->method, $retrievedTrace->request->method);
        $this->assertSame($originalTrace->response->status, $retrievedTrace->response->status);
    }

    public function test_s3_storage_deletes_trace(): void
    {
        $trace = TraceData::fromArray([
            'trace_id' => 'test-s3-delete-' . time(),
            'timestamp' => now()->toISOString(),
            'environment' => 'testing',
            'request' => ['method' => 'DELETE', 'url' => '/test-delete'],
            'response' => ['status' => 204],
        ]);

        $bundlePath = $this->storage->store($trace);

        // Vérifier que la trace existe
        $this->assertNotNull($this->storage->retrieve($bundlePath));

        // Supprimer la trace
        $deleted = $this->storage->delete($bundlePath);
        $this->assertTrue($deleted);

        // Vérifier que la trace n'existe plus
        $this->assertNull($this->storage->retrieve($bundlePath));
    }

    public function test_s3_storage_works_with_path_prefix(): void
    {
        $trace = TraceData::fromArray([
            'trace_id' => 'test-s3-prefix-' . time(),
            'timestamp' => now()->toISOString(),
            'environment' => 'testing',
            'request' => ['method' => 'GET', 'url' => '/test-prefix'],
            'response' => ['status' => 200],
        ]);

        $bundlePath = $this->storage->store($trace);

        // Le chemin devrait contenir la date et être organisé
        $this->assertStringContainsString(date('Y-m-d'), $bundlePath);
        $this->assertStringContainsString($trace->traceId, $bundlePath);
    }

    #[Override]
    protected function tearDown(): void
    {
        // Nettoyer les fichiers de test
        $testDisk = Storage::disk('chronotrace_s3');
        if ($testDisk->exists('.')) {
            $testDisk->deleteDirectory('.');
        }

        parent::tearDown();
    }
}
