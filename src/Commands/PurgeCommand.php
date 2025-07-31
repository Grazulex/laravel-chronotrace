<?php

namespace Grazulex\LaravelChronotrace\Commands;

use Exception;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Console\Command;

class PurgeCommand extends Command
{
    protected $signature = 'chronotrace:purge {--days=30} {--confirm}';

    protected $description = 'Purge old traces';

    public function handle(TraceStorage $storage): int
    {
        $days = (int) $this->option('days');
        $confirm = $this->option('confirm');

        if (! $confirm && ! $this->confirm("Delete traces older than {$days} days?")) {
            $this->info('Purge cancelled.');

            return Command::SUCCESS;
        }

        $this->info("Purging traces older than {$days} days...");

        try {
            $deleted = $storage->purgeOldTraces($days);
            $this->info("Successfully purged {$deleted} traces.");
        } catch (Exception $e) {
            $this->error("Failed to purge traces: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
