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
    $storage = Mockery::mock(TraceStorage::class);
    $storage->shouldReceive('list')
        ->once()
        ->andThrow(new Exception('Storage error'));

    $this->instance(TraceStorage::class, $storage);

    $this->artisan(ListCommand::class)
        ->expectsOutput('Failed to list traces: Storage error')
        ->assertExitCode(1);
});
