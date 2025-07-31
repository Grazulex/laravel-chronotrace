<?php

use Grazulex\LaravelChronotrace\Commands\ListCommand;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;

it('can list traces', function (): void {
    $storage = Mockery::mock(TraceStorage::class);
    $storage->shouldReceive('list')
        ->once()
        ->andReturn([
            [
                'trace_id' => 'abc123def456',
                'size' => 1024,
                'created_at' => time(),
            ],
        ]);

    $this->instance(TraceStorage::class, $storage);

    $this->artisan(ListCommand::class)
        ->expectsOutput('Listing stored traces...')
        ->expectsTable(
            ['Trace ID', 'Size', 'Created At'],
            [['abc123de...', '1,024 bytes', date('Y-m-d H:i:s')]]
        )
        ->assertExitCode(0);
});

it('shows warning when no traces found', function (): void {
    $storage = Mockery::mock(TraceStorage::class);
    $storage->shouldReceive('list')
        ->once()
        ->andReturn([]);

    $this->instance(TraceStorage::class, $storage);

    $this->artisan(ListCommand::class)
        ->expectsOutput('Listing stored traces...')
        ->expectsOutput('No traces found.')
        ->assertExitCode(0);
});

it('handles storage errors gracefully', function (): void {
    $mockStorage = Mockery::mock(TraceStorage::class);
    $mockStorage->shouldReceive('list')
        ->andThrow(new Exception('Storage error'));

    $this->app->instance(TraceStorage::class, $mockStorage);

    $this->artisan('chronotrace:list')
        ->expectsOutput('Listing stored traces...')
        ->expectsOutput('Failed to list traces: Storage error')
        ->assertExitCode(1);
});

it('can show full trace IDs when requested', function (): void {
    $mockTraces = [
        [
            'trace_id' => 'abc12345-def6-7890-abcd-ef1234567890',
            'size' => 1024,
            'created_at' => time(),
        ],
        [
            'trace_id' => 'xyz98765-fed4-3210-zyxw-987654321abc',
            'size' => 2048,
            'created_at' => time() - 3600,
        ],
    ];

    $mockStorage = Mockery::mock(TraceStorage::class);
    $mockStorage->shouldReceive('list')
        ->andReturn($mockTraces);

    $this->app->instance(TraceStorage::class, $mockStorage);

    // Test avec --full-id
    $this->artisan('chronotrace:list', ['--full-id' => true])
        ->expectsOutput('Listing stored traces...')
        ->expectsOutputToContain('abc12345-def6-7890-abcd-ef1234567890') // ID complet
        ->expectsOutputToContain('xyz98765-fed4-3210-zyxw-987654321abc') // ID complet
        ->expectsOutput('Showing 20 of 2 traces.')
        ->assertExitCode(0);
});

it('shows truncated trace IDs by default', function (): void {
    $mockTraces = [
        [
            'trace_id' => 'abc12345-def6-7890-abcd-ef1234567890',
            'size' => 1024,
            'created_at' => time(),
        ],
    ];

    $mockStorage = Mockery::mock(TraceStorage::class);
    $mockStorage->shouldReceive('list')
        ->andReturn($mockTraces);

    $this->app->instance(TraceStorage::class, $mockStorage);

    // Test sans --full-id (comportement par défaut)
    $this->artisan('chronotrace:list')
        ->expectsOutput('Listing stored traces...')
        ->expectsOutputToContain('abc12345...') // ID tronqué
        ->doesntExpectOutputToContain('abc12345-def6-7890-abcd-ef1234567890') // Pas l'ID complet
        ->expectsOutput('Showing 20 of 1 traces.')
        ->assertExitCode(0);
});
