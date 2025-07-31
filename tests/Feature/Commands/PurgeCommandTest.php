<?php

use Grazulex\LaravelChronotrace\Commands\PurgeCommand;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;

it('can purge old traces with confirmation', function (): void {
    $storage = Mockery::mock(TraceStorage::class);
    $storage->shouldReceive('purgeOldTraces')
        ->with(30)
        ->once()
        ->andReturn(5);

    $this->instance(TraceStorage::class, $storage);

    $this->artisan(PurgeCommand::class, ['--confirm' => true])
        ->expectsOutput('Purging traces older than 30 days...')
        ->expectsOutput('Successfully purged 5 traces.')
        ->assertExitCode(0);
});

it('cancels purge without confirmation', function (): void {
    $storage = Mockery::mock(TraceStorage::class);
    $storage->shouldNotReceive('purgeOldTraces');

    $this->instance(TraceStorage::class, $storage);

    $this->artisan(PurgeCommand::class)
        ->expectsConfirmation('Delete traces older than 30 days?', 'no')
        ->expectsOutput('Purge cancelled.')
        ->assertExitCode(0);
});

it('handles purge errors gracefully', function (): void {
    $storage = Mockery::mock(TraceStorage::class);
    $storage->shouldReceive('purgeOldTraces')
        ->once()
        ->andThrow(new Exception('Purge error'));

    $this->instance(TraceStorage::class, $storage);

    $this->artisan(PurgeCommand::class, ['--confirm' => true])
        ->expectsOutput('Failed to purge traces: Purge error')
        ->assertExitCode(1);
});
