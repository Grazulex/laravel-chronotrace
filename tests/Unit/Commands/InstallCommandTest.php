<?php

use Grazulex\LaravelChronotrace\Commands\InstallCommand;
use Mockery;

afterEach(function (): void {
    Mockery::close();
});

it('runs installation successfully', function (): void {
    $this->artisan(InstallCommand::class)
        ->expectsOutputToContain('Installing ChronoTrace...')
        ->expectsOutputToContain('✅ ChronoTrace installation completed!')
        ->expectsOutputToContain('🚀 You can now start using ChronoTrace:')
        ->assertExitCode(0);
});

it('can force overwrite configuration', function (): void {
    $this->artisan(InstallCommand::class, ['--force' => true])
        ->expectsOutputToContain('Installing ChronoTrace...')
        ->expectsOutputToContain('installation completed')
        ->assertExitCode(0);
});

it('shows usage instructions after installation', function (): void {
    $this->artisan(InstallCommand::class)
        ->expectsOutputToContain('php artisan chronotrace:list')
        ->expectsOutputToContain('php artisan chronotrace:record https://example.com')
        ->assertExitCode(0);
});

it('detects Laravel legacy versions', function (): void {
    $this->artisan(InstallCommand::class)
        ->expectsOutputToContain('📱 Detected Laravel')
        ->expectsOutputToContain('installation completed')
        ->assertExitCode(0);
});

it('handles bootstrap app configuration', function (): void {
    $this->artisan(InstallCommand::class)
        ->expectsOutputToContain('Installing ChronoTrace...')
        ->assertExitCode(0);
});
