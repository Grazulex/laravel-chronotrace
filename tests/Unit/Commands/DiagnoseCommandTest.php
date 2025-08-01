<?php

use Grazulex\LaravelChronotrace\Commands\DiagnoseCommand;

it('shows diagnostic information', function (): void {
    $this->artisan(DiagnoseCommand::class)
        ->expectsOutputToContain('🔍 ChronoTrace Configuration Diagnosis')
        ->expectsOutputToContain('General Configuration')
        ->expectsOutputToContain('Storage Configuration')
        ->assertExitCode(0);
});

it('checks queue configuration', function (): void {
    $this->artisan(DiagnoseCommand::class)
        ->expectsOutputToContain('⚡ Queue Configuration')
        ->expectsOutputToContain('queue_connection')
        ->assertExitCode(0);
});

it('shows storage configuration', function (): void {
    $this->artisan(DiagnoseCommand::class)
        ->expectsOutputToContain('💾 Storage Configuration')
        ->assertExitCode(0);
});

it('tests configuration settings', function (): void {
    $this->artisan(DiagnoseCommand::class)
        ->expectsOutputToContain('enabled')
        ->expectsOutputToContain('mode')
        ->expectsOutputToContain('storage')
        ->assertExitCode(0);
});
