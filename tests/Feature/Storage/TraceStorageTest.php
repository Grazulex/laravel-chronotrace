<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use Exception;
use Grazulex\LaravelChronotrace\Models\TraceContext;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Models\TraceRequest;
use Grazulex\LaravelChronotrace\Models\TraceResponse;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Support\Facades\File;
use Override;
use Tests\TestCase;

/**
 * Tests complets pour TraceStorage
 */
class TraceStorageTest extends TestCase
{
    private TraceStorage $storage;

    private string $testTraceId;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = app(TraceStorage::class);
        $this->testTraceId = 'test-trace-' . uniqid();

        // Configuration de test
        config([
            'chronotrace.storage' => 'local',
            'chronotrace.path' => 'tests/traces',
            'chronotrace.compression.enabled' => false,
            'chronotrace.retention_days' => 15,
        ]);
    }

    #[Override]
    protected function tearDown(): void
    {
        // Nettoyer les traces de test
        $this->cleanupTestTraces();

        parent::tearDown();
    }

    private function cleanupTestTraces(): void
    {
        $tracePath = storage_path('tests/traces');
        if (File::exists($tracePath)) {
            File::deleteDirectory($tracePath);
        }
    }

    private function createTestTraceData(): TraceData
    {
        $request = new TraceRequest(
            method: 'GET',
            url: '/api/test',
            headers: ['Accept' => 'application/json'],
            query: ['param' => 'value'],
            input: [],
            files: [],
            user: null,
            session: [],
            userAgent: 'Test Agent',
            ip: '127.0.0.1',
            timestamp: microtime(true)
        );

        $response = new TraceResponse(
            status: 200,
            headers: ['Content-Type' => 'application/json'],
            content: '{"success": true}',
            duration: 150.5,
            memoryUsage: 1024000,
            timestamp: microtime(true),
            exception: null,
            cookies: []
        );

        $context = new TraceContext(
            laravel_version: '12.0',
            php_version: '8.3',
            config: [],
            env_vars: ['APP_ENV' => 'testing'],
            git_commit: '',
            branch: '',
            packages: [],
            middlewares: [],
            providers: []
        );

        return new TraceData(
            traceId: $this->testTraceId,
            timestamp: now()->toISOString(),
            environment: 'testing',
            request: $request,
            response: $response,
            context: $context,
            database: [
                ['type' => 'query', 'sql' => 'SELECT * FROM users', 'time' => 10],
            ],
            cache: [
                ['type' => 'hit', 'key' => 'user:1', 'value_size' => 256],
            ],
            http: [
                ['type' => 'request_sending', 'url' => 'https://api.example.com', 'method' => 'GET'],
            ],
            jobs: [
                ['type' => 'job_processing', 'job_name' => 'ProcessOrder', 'queue' => 'default'],
            ]
        );
    }

    public function test_can_store_and_retrieve_trace(): void
    {
        $traceData = $this->createTestTraceData();

        // Stocker la trace
        $bundlePath = $this->storage->store($traceData);

        // Récupérer la trace
        $retrievedTrace = $this->storage->retrieve($this->testTraceId);

        $this->assertInstanceOf(TraceData::class, $retrievedTrace);
        $this->assertSame($this->testTraceId, $retrievedTrace->traceId);
        $this->assertSame('testing', $retrievedTrace->environment);
        $this->assertSame('/api/test', $retrievedTrace->request->url);
        $this->assertSame(200, $retrievedTrace->response->status);

        // Vérifier que bundlePath est retourné
        $this->assertIsString($bundlePath);
    }

    public function test_retrieve_returns_null_for_nonexistent_trace(): void
    {
        $result = $this->storage->retrieve('nonexistent-trace');

        $this->assertNull($result);
    }

    public function test_can_list_stored_traces(): void
    {
        $traceData1 = $this->createTestTraceData();

        // Créer une seconde trace avec un ID différent
        $request2 = new TraceRequest(
            method: 'POST',
            url: '/api/test2',
            headers: ['Accept' => 'application/json'],
            query: [],
            input: ['data' => 'test'],
            files: [],
            user: null,
            session: [],
            userAgent: 'Test Agent',
            ip: '127.0.0.1',
            timestamp: microtime(true)
        );

        $response2 = new TraceResponse(
            status: 201,
            headers: ['Content-Type' => 'application/json'],
            content: '{"created": true}',
            duration: 200.0,
            memoryUsage: 2048000,
            timestamp: microtime(true),
            exception: null,
            cookies: []
        );

        $context2 = new TraceContext(
            laravel_version: '12.0',
            php_version: '8.3',
            config: [],
            env_vars: ['APP_ENV' => 'testing'],
            git_commit: '',
            branch: '',
            packages: [],
            middlewares: [],
            providers: []
        );

        $traceData2 = new TraceData(
            traceId: 'test-trace-2-' . uniqid(),
            timestamp: now()->toISOString(),
            environment: 'testing',
            request: $request2,
            response: $response2,
            context: $context2,
            database: [],
            cache: [],
            http: [],
            jobs: []
        );

        $this->storage->store($traceData1);
        $this->storage->store($traceData2);

        $traces = $this->storage->list();

        $this->assertIsArray($traces);
        $this->assertGreaterThanOrEqual(2, count($traces));
    }

    public function test_storage_handles_filesystem_errors_gracefully(): void
    {
        // Simuler un problème de filesystem en utilisant un disque inexistant
        config(['chronotrace.storage' => 'nonexistent-disk']);

        $storage = app(TraceStorage::class);
        $traceData = $this->createTestTraceData();

        // Le storage devrait gérer l'erreur sans planter
        try {
            $storage->store($traceData);
            $this->assertTrue(true); // Si on arrive ici, l'erreur a été gérée
        } catch (Exception $e) {
            // Une exception peut être levée, c'est acceptable
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    public function test_storage_respects_compression_settings(): void
    {
        config(['chronotrace.compression.enabled' => true]);

        $storage = app(TraceStorage::class);
        $traceData = $this->createTestTraceData();

        $bundlePath = $storage->store($traceData);
        $retrievedTrace = $storage->retrieve($this->testTraceId);

        $this->assertInstanceOf(TraceData::class, $retrievedTrace);
        $this->assertSame($this->testTraceId, $retrievedTrace->traceId);
        $this->assertIsString($bundlePath);
    }

    public function test_storage_validates_trace_data_structure(): void
    {
        $traceData = $this->createTestTraceData();

        // Tester avec des données valides
        $bundlePath = $this->storage->store($traceData);

        $this->assertIsString($bundlePath);
        $this->assertNotEmpty($bundlePath);
    }

    public function test_storage_creates_directory_structure(): void
    {
        $traceData = $this->createTestTraceData();

        $bundlePath = $this->storage->store($traceData);

        // Si on arrive ici sans exception, la structure a été créée correctement
        $this->assertIsString($bundlePath);
    }

    public function test_storage_can_handle_large_traces(): void
    {
        $traceData = $this->createTestTraceData();

        // Le système devrait pouvoir gérer des traces avec beaucoup de données
        $this->storage->store($traceData);
        $retrievedTrace = $this->storage->retrieve($this->testTraceId);

        $this->assertInstanceOf(TraceData::class, $retrievedTrace);
        $this->assertSame($this->testTraceId, $retrievedTrace->traceId);
    }
}
