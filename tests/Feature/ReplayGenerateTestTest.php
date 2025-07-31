<?php

use Grazulex\LaravelChronotrace\Commands\ReplayCommand;
use Grazulex\LaravelChronotrace\Models\TraceContext;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Models\TraceRequest;
use Grazulex\LaravelChronotrace\Models\TraceResponse;

// Test de génération de test Pest à partir d'une trace
it('can generate a pest test from trace', function (): void {
    // Créer une trace d'exemple
    $trace = new TraceData(
        traceId: 'test-trace-12345',
        timestamp: now()->toISOString(),
        environment: 'testing',
        request: new TraceRequest(
            method: 'POST',
            url: '/api/users',
            headers: ['Content-Type' => 'application/json'],
            query: [],
            input: ['name' => 'John Doe', 'email' => 'john@example.com'],
            files: [],
            user: null,
            session: [],
            userAgent: 'Test Agent',
            ip: '127.0.0.1',
            timestamp: microtime(true)
        ),
        response: new TraceResponse(
            status: 201,
            headers: ['Content-Type' => 'application/json'],
            content: '{"id": 123, "name": "John Doe"}',
            duration: 0.15,
            memoryUsage: 1024,
            timestamp: microtime(true),
            exception: null,
            cookies: []
        ),
        context: new TraceContext(
            laravel_version: '11.0',
            php_version: '8.3.0',
            config: [],
            env_vars: []
        )
    );

    // Utiliser reflection pour accéder aux méthodes privées
    $reflectionClass = new ReflectionClass(ReplayCommand::class);
    $buildTestMethod = $reflectionClass->getMethod('buildPestTestContent');
    $buildTestMethod->setAccessible(true);

    $command = new ReplayCommand;

    // Créer le dossier de test
    $testDir = 'tests/Generated';
    if (! is_dir($testDir)) {
        mkdir($testDir, 0755, true);
    }

    // Exécuter la méthode de construction
    $testContent = $buildTestMethod->invoke($command, $trace);

    // Vérifier le contenu généré
    $this->assertStringContainsString("it('trace replay for POST /api/users'", $testContent);
    $this->assertStringContainsString('$this->post(\'/api/users\'', $testContent);
    $this->assertStringContainsString('$response->assertStatus(201)', $testContent);
    $this->assertStringContainsString('Generated Pest test from ChronoTrace', $testContent);
    $this->assertStringContainsString('Trace ID: test-trace-12345', $testContent);

    // Écrire le fichier pour tester le format complet
    $testFile = $testDir . '/TestGenerated.php';
    file_put_contents($testFile, $testContent);

    // Vérifier que le fichier est du PHP valide
    $this->assertFileExists($testFile);
    $content = file_get_contents($testFile);
    $this->assertStringStartsWith('<?php', $content);

    // Nettoyer
    if (file_exists($testFile)) {
        unlink($testFile);
    }
    if (is_dir($testDir)) {
        rmdir($testDir);
    }

    expect($testContent)
        ->toContain('trace replay for POST /api/users')
        ->toContain('$response->assertStatus(201)')
        ->toContain('uses(Tests\\TestCase::class, RefreshDatabase::class)')
        ->toContain('performs within acceptable time limits');
});
