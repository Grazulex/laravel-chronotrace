<?php

use Grazulex\LaravelChronotrace\Models\TraceContext;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Models\TraceRequest;
use Grazulex\LaravelChronotrace\Models\TraceResponse;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function makePortableTrace(string $traceId): TraceData
{
    return new TraceData(
        traceId: $traceId,
        timestamp: now()->toISOString(),
        environment: 'production',
        request: new TraceRequest(
            method: 'POST',
            url: '/api/orders',
            headers: ['Accept' => 'application/json'],
            query: [],
            input: ['sku' => 'ABC'],
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
            content: '{"created": true}',
            duration: 42.0,
            memoryUsage: 1024,
            timestamp: microtime(true),
            exception: null,
            cookies: []
        ),
        context: new TraceContext(
            laravel_version: '12.0',
            php_version: '8.3',
            config: [],
            env_vars: [],
            git_commit: '',
            branch: '',
            packages: [],
            middlewares: [],
            providers: []
        ),
        database: [['type' => 'query', 'sql' => 'INSERT INTO orders', 'time' => 3]],
    );
}

function makeArchive(string $path, ?string $manifest): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($manifest !== null) {
        $zip->addFromString('manifest.json', $manifest);
    } else {
        $zip->addFromString('other.txt', 'nothing here');
    }
    $zip->close();
}

beforeEach(function (): void {
    Storage::fake('local');
    config(['chronotrace.storage' => 'local']);
    $this->app->forgetInstance(TraceStorage::class);

    $this->exportDir = sys_get_temp_dir() . '/chronotrace_export_test_' . uniqid();
    mkdir($this->exportDir, 0755, true);
});

afterEach(function (): void {
    File::deleteDirectory($this->exportDir);
});

it('exports a trace and imports it back on another storage', function (): void {
    $storage = app(TraceStorage::class);
    $storage->store(makePortableTrace('ct_portable_1'));
    $archive = $this->exportDir . '/ct_portable_1.zip';

    $this->artisan('chronotrace:export', ['id' => 'ct_portable_1', '--output' => $this->exportDir])
        ->expectsOutput("Trace ct_portable_1 exported to {$archive}")
        ->assertExitCode(0);

    expect(file_exists($archive))->toBeTrue();

    // Simulate another machine: empty storage
    Storage::fake('local');
    expect($storage->retrieve('ct_portable_1'))->toBeNull();

    $this->artisan('chronotrace:import', ['file' => $archive])
        ->expectsOutput('Trace ct_portable_1 imported.')
        ->assertExitCode(0);

    $trace = $storage->retrieve('ct_portable_1');
    expect($trace)->toBeInstanceOf(TraceData::class)
        ->and($trace->environment)->toBe('production')
        ->and($trace->request->url)->toBe('/api/orders')
        ->and($trace->response->status)->toBe(201)
        ->and($trace->database)->toHaveCount(1);
});

it('writes to an explicit archive file path', function (): void {
    app(TraceStorage::class)->store(makePortableTrace('ct_portable_2'));
    $archive = $this->exportDir . '/nested/my-trace.zip';

    $this->artisan('chronotrace:export', ['id' => 'ct_portable_2', '--output' => $archive])
        ->assertExitCode(0);

    expect(file_exists($archive))->toBeTrue();
});

it('exports traces stored on a previous day', function (): void {
    $storage = app(TraceStorage::class);
    $storage->store(makePortableTrace('ct_old_trace'));

    $todayPath = 'traces/' . date('Y-m-d') . '/ct_old_trace.zip';
    Storage::disk('local')->move($todayPath, 'traces/2020-01-01/ct_old_trace.zip');

    expect($storage->findBundlePath('ct_old_trace'))->toBe('traces/2020-01-01/ct_old_trace.zip');

    $this->artisan('chronotrace:export', ['id' => 'ct_old_trace', '--output' => $this->exportDir])
        ->assertExitCode(0);

    expect(file_exists($this->exportDir . '/ct_old_trace.zip'))->toBeTrue();
});

it('fails to export an unknown trace', function (): void {
    $this->artisan('chronotrace:export', ['id' => 'ct_missing', '--output' => $this->exportDir])
        ->expectsOutput('Failed to export trace: Trace ct_missing not found.')
        ->assertExitCode(1);
});

it('refuses to overwrite an existing archive without --force', function (): void {
    app(TraceStorage::class)->store(makePortableTrace('ct_portable_3'));
    $archive = $this->exportDir . '/ct_portable_3.zip';
    file_put_contents($archive, 'existing');

    $this->artisan('chronotrace:export', ['id' => 'ct_portable_3', '--output' => $this->exportDir])
        ->expectsOutput("File {$archive} already exists. Use --force to overwrite it.")
        ->assertExitCode(1);

    expect(file_get_contents($archive))->toBe('existing');

    $this->artisan('chronotrace:export', ['id' => 'ct_portable_3', '--output' => $this->exportDir, '--force' => true])
        ->assertExitCode(0);

    expect(file_get_contents($archive))->not->toBe('existing');
});

it('refuses to import over an existing trace without --force', function (): void {
    $storage = app(TraceStorage::class);
    $storage->store(makePortableTrace('ct_portable_4'));
    $archive = $storage->export('ct_portable_4', $this->exportDir . '/ct_portable_4.zip');

    $this->artisan('chronotrace:import', ['file' => $archive])
        ->expectsOutput('Failed to import trace: Trace ct_portable_4 already exists. Use --force to overwrite it.')
        ->assertExitCode(1);

    $this->artisan('chronotrace:import', ['file' => $archive, '--force' => true])
        ->expectsOutput('Trace ct_portable_4 imported.')
        ->assertExitCode(0);

    expect($storage->retrieve('ct_portable_4'))->toBeInstanceOf(TraceData::class);
});

it('fails to import a missing file', function (): void {
    $file = $this->exportDir . '/missing.zip';

    $this->artisan('chronotrace:import', ['file' => $file])
        ->expectsOutput("Failed to import trace: Archive {$file} not found or not readable.")
        ->assertExitCode(1);
});

it('rejects a file that is not a zip archive', function (): void {
    $file = $this->exportDir . '/not-a-zip.zip';
    file_put_contents($file, 'plain text');

    $this->artisan('chronotrace:import', ['file' => $file])
        ->expectsOutput("Failed to import trace: {$file} is not a valid trace archive.")
        ->assertExitCode(1);
});

it('rejects an archive without manifest', function (): void {
    $file = $this->exportDir . '/no-manifest.zip';
    makeArchive($file, null);

    $this->artisan('chronotrace:import', ['file' => $file])
        ->expectsOutput("Failed to import trace: {$file} does not contain a trace manifest.")
        ->assertExitCode(1);
});

it('rejects an archive with an invalid manifest', function (): void {
    $file = $this->exportDir . '/bad-manifest.zip';
    makeArchive($file, '{"trace_id": "ct_x"}');

    $this->artisan('chronotrace:import', ['file' => $file])
        ->expectsOutput("Failed to import trace: {$file} contains an invalid trace manifest.")
        ->assertExitCode(1);
});

it('rejects an archive whose trace ID could escape the storage directory', function (): void {
    $file = $this->exportDir . '/traversal.zip';
    makeArchive($file, json_encode([
        'trace_id' => '../../evil',
        'timestamp' => '2026-01-01T00:00:00Z',
        'environment' => 'production',
    ]));

    $this->artisan('chronotrace:import', ['file' => $file])
        ->expectsOutput("Failed to import trace: {$file} contains an invalid trace ID.")
        ->assertExitCode(1);

    expect(Storage::disk('local')->allFiles())->toBe([]);
});
