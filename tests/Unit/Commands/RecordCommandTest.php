<?php

use Grazulex\LaravelChronotrace\Commands\RecordCommand;

beforeEach(function (): void {
    config(['chronotrace.enabled' => true]);
});

it('handles invalid JSON data', function (): void {
    $this->artisan(RecordCommand::class, [
        'url' => 'https://httpbin.org/get',
        '--data' => 'invalid-json',
    ])
        ->expectsOutputToContain('Invalid JSON in --data option')
        ->assertExitCode(1);
});

it('can instantiate command', function (): void {
    $command = new RecordCommand;
    expect($command)->toBeInstanceOf(RecordCommand::class);
});

it('has correct signature', function (): void {
    $command = new RecordCommand;
    expect($command->getName())->toBe('chronotrace:record');
});
