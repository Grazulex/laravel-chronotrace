<?php

namespace Grazulex\LaravelChronotrace\Commands;

use Exception;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'chronotrace:list {--limit=20}';

    protected $description = 'List stored traces';

    public function handle(TraceStorage $storage): int
    {
        $limit = (int) $this->option('limit');

        $this->info('Listing stored traces...');

        try {
            $traces = $storage->list();

            if ($traces === []) {
                $this->warn('No traces found.');

                return Command::SUCCESS;
            }

            $this->table(
                ['Trace ID', 'Size', 'Created At'],
                array_slice(array_map(function (mixed $trace): array {
                    if (! is_array($trace)) {
                        return ['Unknown', 'Unknown', 'Unknown'];
                    }

                    $timestamp = is_numeric($trace['created_at']) ? (int) $trace['created_at'] : time();
                    $traceId = isset($trace['trace_id']) && is_string($trace['trace_id']) ? $trace['trace_id'] : 'unknown';

                    return [
                        substr($traceId, 0, 8) . '...',
                        number_format($trace['size']) . ' bytes',
                        date('Y-m-d H:i:s', $timestamp),
                    ];
                }, $traces), 0, $limit)
            );

            $total = count($traces);
            $this->info("Showing {$limit} of {$total} traces.");
        } catch (Exception $e) {
            $this->error("Failed to list traces: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
