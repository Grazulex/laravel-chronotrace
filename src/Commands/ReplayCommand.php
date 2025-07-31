<?php

namespace Grazulex\LaravelChronotrace\Commands;

use Exception;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Console\Command;

class ReplayCommand extends Command
{
    protected $signature = 'chronotrace:replay {trace-id}';

    protected $description = 'Replay a stored trace';

    public function handle(TraceStorage $storage): int
    {
        $traceId = $this->argument('trace-id');

        $this->info("Replaying trace {$traceId}...");

        try {
            $trace = $storage->retrieve($traceId);

            if (! $trace instanceof TraceData) {
                $this->error("Trace {$traceId} not found.");

                return Command::FAILURE;
            }

            // TODO: Implement deterministic replay
            $this->warn('Replay functionality not yet implemented.');

            $this->info('Trace data retrieved successfully:');
            $this->line("Environment: {$trace->environment}");
            $this->line("Timestamp: {$trace->timestamp}");
            $this->line("Request URL: {$trace->request->url}");
            $this->line("Response Status: {$trace->response->status}");
        } catch (Exception $e) {
            $this->error("Failed to replay trace: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
