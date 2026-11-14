<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Commands;

use Exception;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Console\Command;

class ExportCommand extends Command
{
    protected $signature = 'chronotrace:export {id : The ID of the trace to export}
                                        {--output= : Archive file or directory to write to (defaults to <id>.zip in the current directory)}
                                        {--force : Overwrite the archive if it already exists}';

    protected $description = 'Export a stored trace to a portable archive';

    public function handle(TraceStorage $storage): int
    {
        $traceId = $this->argument('id');
        $destination = $this->resolveDestination($traceId);

        if (file_exists($destination) && ! $this->option('force')) {
            $this->error("File {$destination} already exists. Use --force to overwrite it.");

            return Command::FAILURE;
        }

        try {
            $path = $storage->export($traceId, $destination);
        } catch (Exception $e) {
            $this->error("Failed to export trace: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $this->info("Trace {$traceId} exported to {$path}");
        $this->line("Import it elsewhere with: php artisan chronotrace:import {$path}");

        return Command::SUCCESS;
    }

    private function resolveDestination(string $traceId): string
    {
        $output = $this->option('output');
        $fileName = $traceId . '.zip';

        if (! is_string($output) || $output === '') {
            $cwd = getcwd();

            return ($cwd === false ? '.' : $cwd) . DIRECTORY_SEPARATOR . $fileName;
        }

        if (is_dir($output)) {
            return rtrim($output, '/\\') . DIRECTORY_SEPARATOR . $fileName;
        }

        return $output;
    }
}
