<?php

namespace Grazulex\LaravelChronotrace\Tests\Integration;

use Grazulex\LaravelChronotrace\Commands\ListCommand;
use Grazulex\LaravelChronotrace\Commands\PurgeCommand;
use Grazulex\LaravelChronotrace\Commands\RecordCommand;
use Grazulex\LaravelChronotrace\Commands\ReplayCommand;
use Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Orchestra\Testbench\TestCase;

class ArtisanCommandsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelChronotraceServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('chronotrace.enabled', true);
        $app['config']->set('chronotrace.storage.disk', 'local');
        $app['config']->set('chronotrace.storage.path', 'tests/traces');
    }

    public function test_chronotrace_commands_are_registered(): void
    {
        $commands = [
            'chronotrace:list',
            'chronotrace:purge',
            'chronotrace:record',
            'chronotrace:replay',
        ];

        $kernel = $this->app->make(Kernel::class);
        $allCommands = $kernel->all();

        foreach ($commands as $command) {
            $this->assertArrayHasKey($command, $allCommands, "Command {$command} should be registered");
        }
    }

    public function test_list_command_shows_no_traces_initially(): void
    {
        $this->artisan(ListCommand::class)
            ->expectsOutput('Listing stored traces...')
            ->expectsOutput('No traces found.')
            ->assertExitCode(0);
    }

    public function test_purge_command_can_be_cancelled(): void
    {
        $this->artisan(PurgeCommand::class)
            ->expectsConfirmation('Delete traces older than 30 days?', 'no')
            ->expectsOutput('Purge cancelled.')
            ->assertExitCode(0);
    }

    public function test_purge_command_with_confirm_flag(): void
    {
        $this->artisan(PurgeCommand::class, ['--confirm' => true])
            ->expectsOutput('Purging traces older than 30 days...')
            ->expectsOutput('Successfully purged 0 traces.')
            ->assertExitCode(0);
    }

    public function test_record_command_warns_not_implemented(): void
    {
        $this->artisan(RecordCommand::class, ['url' => 'https://example.com'])
            ->expectsOutput('Recording trace for GET https://example.com...')
            ->expectsOutput('Recording functionality not yet implemented.')
            ->assertExitCode(0);
    }

    public function test_record_command_with_options(): void
    {
        $this->artisan(RecordCommand::class, [
            'url' => 'https://api.example.com/users',
            '--method' => 'POST',
            '--data' => '{"name": "John"}',
            '--timeout' => '60',
        ])
            ->expectsOutput('Recording trace for POST https://api.example.com/users...')
            ->expectsOutput('Recording functionality not yet implemented.')
            ->assertExitCode(0);
    }

    public function test_replay_command_with_missing_trace(): void
    {
        $this->artisan(ReplayCommand::class, ['trace-id' => 'nonexistent'])
            ->expectsOutput('Replaying trace nonexistent...')
            ->expectsOutput('Trace nonexistent not found.')
            ->assertExitCode(1);
    }

    public function test_help_for_commands(): void
    {
        $commands = [
            'chronotrace:list',
            'chronotrace:purge',
            'chronotrace:record',
            'chronotrace:replay',
        ];

        foreach ($commands as $command) {
            // Juste vérifier que la commande existe et peut être exécutée
            $result = $this->artisan($command, ['--help' => true]);
            $result->assertExitCode(0);
        }
    }

    public function test_list_command_respects_limit_option(): void
    {
        $this->artisan(ListCommand::class, ['--limit' => '5'])
            ->expectsOutput('Listing stored traces...')
            ->expectsOutput('No traces found.')
            ->assertExitCode(0);
    }

    public function test_purge_command_respects_days_option(): void
    {
        $this->artisan(PurgeCommand::class, ['--days' => '7', '--confirm' => true])
            ->expectsOutput('Purging traces older than 7 days...')
            ->expectsOutput('Successfully purged 0 traces.')
            ->assertExitCode(0);
    }
}
