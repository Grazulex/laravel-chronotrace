<?php

use Grazulex\LaravelChronotrace\Commands\ReplayCommand;
use Grazulex\LaravelChronotrace\Models\TraceContext;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Models\TraceRequest;
use Grazulex\LaravelChronotrace\Models\TraceResponse;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Support\Facades\Storage;

describe('ReplayCommand Enhanced Pest Generation', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
        $this->traceStorage = app(TraceStorage::class);
    });

    it('generates improved pest test with proper imports', function (): void {
        $trace = new TraceData(
            traceId: 'test_trace_123',
            timestamp: '2025-01-01T00:00:00+00:00',
            environment: 'testing',
            request: new TraceRequest(
                method: 'GET',
                url: 'http://localhost:8080/api/test',
                headers: ['accept' => ['application/json'], 'host' => ['localhost:8080']],
                query: [],
                input: [],
                files: [],
                user: null,
                session: [],
                userAgent: 'curl/8.5.0',
                ip: '127.0.0.1',
                timestamp: microtime(true)
            ),
            response: new TraceResponse(
                status: 200,
                headers: ['content-type' => ['application/json']],
                content: '{"message":"success","data":{"count":5}}',
                duration: 0.05,
                memoryUsage: 1024000,
                timestamp: microtime(true),
                exception: null,
                cookies: []
            ),
            context: new TraceContext(
                laravel_version: '11.0',
                php_version: '8.3.0',
                config: [],
                env_vars: [],
                git_commit: '',
                branch: '',
                packages: [],
                middlewares: [],
                providers: []
            ),
            database: [['query' => 'SELECT * FROM users', 'time' => 0.01]],
            cache: [['type' => 'hit', 'key' => 'test_cache_key']],
            http: [],
            mail: [],
            notifications: [],
            events: [],
            jobs: [],
            filesystem: []
        );

        $this->traceStorage->store($trace);

        $this->artisan(ReplayCommand::class, [
            'trace-id' => 'test_trace_123',
            '--generate-test' => true,
            '--test-path' => 'tests/Generated',
        ])
            ->expectsOutput('Replaying trace test_trace_123...')
            ->expectsOutputToContain('✅ Pest test generated:')
            ->assertExitCode(0);

        // Vérifier le contenu du fichier généré
        $testFile = 'tests/Generated/ChronoTrace_test_tra_Test.php';
        expect(file_exists($testFile))->toBeTrue();

        $content = file_get_contents($testFile);

        // Vérifier les imports nécessaires
        expect($content)->toContain('use Illuminate\Foundation\Testing\RefreshDatabase;');
        expect($content)->toContain('use Illuminate\Support\Facades\Cache;');
        expect($content)->toContain('use Illuminate\Support\Facades\DB;');

        // Vérifier l'utilisation de minuscules pour la méthode HTTP
        expect($content)->toContain('$this->get(');
        expect($content)->not->toContain('$this->GET(');

        // Vérifier l'utilisation du path au lieu de l'URL complète
        expect($content)->toContain("'/api/test'");
        expect($content)->not->toContain("'http://localhost:8080/api/test'");

        // Vérifier les assertions améliorées
        expect($content)->toContain('assertJsonStructure');
        expect($content)->toContain('DB::getQueryLog()');

        // Nettoyer
        if (file_exists($testFile)) {
            unlink($testFile);
        }
        if (is_dir('tests/Generated')) {
            rmdir('tests/Generated');
        }
    });

    it('outputs trace as JSON format', function (): void {
        $trace = new TraceData(
            traceId: 'test_json_123',
            timestamp: '2025-01-01T00:00:00+00:00',
            environment: 'testing',
            request: new TraceRequest(
                method: 'POST',
                url: 'http://localhost/api/users',
                headers: ['content-type' => ['application/json']],
                query: [],
                input: ['name' => 'John Doe'],
                files: [],
                user: null,
                session: [],
                userAgent: 'PHPUnit',
                ip: '127.0.0.1',
                timestamp: microtime(true)
            ),
            response: new TraceResponse(
                status: 201,
                headers: ['content-type' => ['application/json']],
                content: '{"id":1,"name":"John Doe"}',
                duration: 0.1,
                memoryUsage: 2048000,
                timestamp: microtime(true),
                exception: null,
                cookies: []
            ),
            context: new TraceContext(
                laravel_version: '11.0',
                php_version: '8.3.0',
                config: [],
                env_vars: [],
                git_commit: '',
                branch: '',
                packages: [],
                middlewares: [],
                providers: []
            ),
            database: [],
            cache: [],
            http: [],
            mail: [],
            notifications: [],
            events: [],
            jobs: [],
            filesystem: []
        );

        $this->traceStorage->store($trace);

        // Test simple pour voir si le format JSON fonctionne
        $this->artisan(ReplayCommand::class, [
            'trace-id' => 'test_json_123',
            '--format' => 'json',
        ])
            ->expectsOutputToContain('test_json_123') // Au minimum le trace ID devrait apparaître
            ->assertExitCode(0);
    });

    it('handles raw format output', function (): void {
        $trace = new TraceData(
            traceId: 'test_raw_123',
            timestamp: '2025-01-01T00:00:00+00:00',
            environment: 'testing',
            request: new TraceRequest(
                method: 'GET',
                url: 'http://localhost/',
                headers: [],
                query: [],
                input: [],
                files: [],
                user: null,
                session: [],
                userAgent: 'Test',
                ip: '127.0.0.1',
                timestamp: microtime(true)
            ),
            response: new TraceResponse(
                status: 200,
                headers: [],
                content: 'OK',
                duration: 0.01,
                memoryUsage: 1024,
                timestamp: microtime(true),
                exception: null,
                cookies: []
            ),
            context: new TraceContext(
                laravel_version: '11.0',
                php_version: '8.3.0',
                config: [],
                env_vars: [],
                git_commit: '',
                branch: '',
                packages: [],
                middlewares: [],
                providers: []
            ),
            database: [],
            cache: [],
            http: [],
            mail: [],
            notifications: [],
            events: [],
            jobs: [],
            filesystem: []
        );

        $this->traceStorage->store($trace);

        $this->artisan(ReplayCommand::class, [
            'trace-id' => 'test_raw_123',
            '--format' => 'raw',
        ])
            ->assertExitCode(0);
    });
});
