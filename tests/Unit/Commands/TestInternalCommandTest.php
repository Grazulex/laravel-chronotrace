<?php

use Grazulex\LaravelChronotrace\Commands\TestInternalCommand;

beforeEach(function (): void {
    config(['chronotrace.enabled' => true]);
});

it('can execute test internal command', function (): void {
    $this->artisan(TestInternalCommand::class)
        ->expectsOutputToContain('🧪 Testing ChronoTrace with internal Laravel operations...')
        ->expectsOutputToContain('🗄️  Testing database operations...')
        ->expectsOutputToContain('💾 Testing cache operations...')
        ->expectsOutputToContain('📡 Testing custom events...')
        ->expectsOutputToContain('✅ Internal operations test completed!')
        ->assertExitCode(0);
});

it('respects chronotrace disabled configuration', function (): void {
    config(['chronotrace.enabled' => false]);

    $this->artisan(TestInternalCommand::class)
        ->expectsOutputToContain('⚠️  ChronoTrace is disabled')
        ->assertExitCode(1);
});

it('can run with specific options', function (): void {
    $this->artisan(TestInternalCommand::class, ['--with-db' => true])
        ->expectsOutputToContain('🗄️  Testing database operations...')
        ->expectsOutputToContain('✅ Internal operations test completed!')
        ->assertExitCode(0);
});

it('can run cache operations only', function (): void {
    $this->artisan(TestInternalCommand::class, ['--with-cache' => true])
        ->expectsOutputToContain('💾 Testing cache operations...')
        ->expectsOutputToContain('✅ Internal operations test completed!')
        ->assertExitCode(0);
});

it('can run events only', function (): void {
    $this->artisan(TestInternalCommand::class, ['--with-events' => true])
        ->expectsOutputToContain('📡 Testing custom events...')
        ->expectsOutputToContain('✅ Internal operations test completed!')
        ->assertExitCode(0);
});

it('has correct command signature', function (): void {
    $command = new TestInternalCommand;
    expect($command->getName())->toBe('chronotrace:test-internal');
    expect($command->getDescription())->toContain('Test ChronoTrace with internal Laravel operations');
});

it('provides helpful usage instructions', function (): void {
    $this->artisan(TestInternalCommand::class)
        ->expectsOutputToContain('php artisan chronotrace:replay')
        ->expectsOutputToContain('Trace ID:')
        ->assertExitCode(0);
});
