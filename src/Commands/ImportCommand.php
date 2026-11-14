<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Commands;

use Exception;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'chronotrace:import {file : Path of the archive produced by chronotrace:export}
                                        {--force : Overwrite the trace if it already exists}';

    protected $description = 'Import a trace archive exported from another machine';

    public function handle(TraceStorage $storage): int
    {
        $file = $this->argument('file');

        try {
            $traceId = $storage->import($file, (bool) $this->option('force'));
        } catch (Exception $e) {
            $this->error("Failed to import trace: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $this->info("Trace {$traceId} imported.");
        $this->line("Replay it with: php artisan chronotrace:replay {$traceId}");

        return Command::SUCCESS;
    }
}
