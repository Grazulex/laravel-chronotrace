<?php

use Grazulex\LaravelChronotrace\Commands\MiddlewareTestCommand;

describe('MiddlewareTestCommand', function (): void {
    it('can execute middleware test command', function (): void {
        $this->artisan(MiddlewareTestCommand::class)
            ->expectsOutput('🧪 Testing ChronoTrace Middleware Installation')
            ->assertExitCode(0);
    });

    it('reports configuration status', function (): void {
        // The middleware logs debug traces with error_log(). PHPUnit 12 redirects
        // error_log() to its own file right before each test and reports what it
        // finds there as unexpected output (a risky test), so redirect it here.
        ini_set('error_log', sys_get_temp_dir() . '/chronotrace-tests.log');

        config(['chronotrace.enabled' => true]);
        config(['chronotrace.mode' => 'always']);
        config(['chronotrace.debug' => true]);

        $this->artisan(MiddlewareTestCommand::class)
            ->expectsOutput('📋 Configuration Check:')
            ->expectsOutputToContain('chronotrace.enabled: true')
            ->expectsOutputToContain('chronotrace.mode: always')
            ->expectsOutputToContain('chronotrace.debug: true')
            ->assertExitCode(0);
    });

    it('can instantiate middleware class', function (): void {
        $this->artisan(MiddlewareTestCommand::class)
            ->expectsOutputToContain('Middleware class can be instantiated')
            ->assertExitCode(0);
    });

    it('provides recommendations when chronotrace is disabled', function (): void {
        config(['chronotrace.enabled' => false]);

        $this->artisan(MiddlewareTestCommand::class)
            ->expectsOutputToContain('Enable ChronoTrace: Set CHRONOTRACE_ENABLED=true')
            ->assertExitCode(0);
    });

    it('provides recommendations when debug is disabled', function (): void {
        config(['chronotrace.debug' => false]);

        $this->artisan(MiddlewareTestCommand::class)
            ->expectsOutputToContain('Enable debug mode: Set CHRONOTRACE_DEBUG=true')
            ->assertExitCode(0);
    });

    it('can simulate request processing', function (): void {
        config(['chronotrace.enabled' => true]);

        $this->artisan(MiddlewareTestCommand::class)
            ->expectsOutputToContain('Simulating GET /test request')
            ->expectsOutputToContain('Middleware processed request successfully')
            ->assertExitCode(0);
    });
});
