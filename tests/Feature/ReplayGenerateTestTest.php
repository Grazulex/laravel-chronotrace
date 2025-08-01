<?php

use Grazulex\LaravelChronotrace\Display\TestGenerators\PestTestGenerator;
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

    // Utiliser le générateur PestTestGenerator directement
    $generator = new PestTestGenerator;

    // Créer le dossier de test
    $testDir = 'tests/Generated';
    if (! is_dir($testDir)) {
        mkdir($testDir, 0755, true);
    }

    // Générer le fichier de test
    $testFile = $generator->generate($trace, $testDir);

    // Vérifier que le fichier a été créé
    expect(file_exists($testFile))->toBeTrue();

    // Lire le contenu du fichier généré
    $testContent = file_get_contents($testFile);

    // Vérifier le contenu généré
    expect($testContent)->toContain("it('trace replay for POST /api/users'");
    expect($testContent)->toContain('$this->post(\'/api/users\'');
    expect($testContent)->toContain('$response->assertStatus(201)');
    expect($testContent)->toContain('Generated Pest test from ChronoTrace');
    expect($testContent)->toContain('Trace ID: test-trace-12345');
    $content = file_get_contents($testFile);
    $this->assertStringStartsWith('<?php', $content);

    // Nettoyer récursivement
    if (file_exists($testFile)) {
        unlink($testFile);
    }
    if (is_dir($testDir)) {
        // Supprimer tous les fichiers du répertoire d'abord
        $files = glob($testDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($testDir);
    }

    expect($testContent)
        ->toContain('trace replay for POST /api/users')
        ->toContain('$response->assertStatus(201)')
        ->toContain('uses(Tests\\TestCase::class, RefreshDatabase::class)')
        ->toContain('performs within acceptable time limits');
});
