<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Commands;

use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Services\TraceComparator;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Console\Command;

class DiffCommand extends Command
{
    protected $signature = 'chronotrace:diff {id1 : The ID of the reference trace}
                                        {id2 : The ID of the trace to compare against it}
                                        {--threshold=0 : Ignore duration differences up to this many milliseconds}
                                        {--json : Output the differences as JSON}';

    protected $description = 'Compare two traces (request, status, duration and outgoing HTTP calls)';

    public function handle(TraceStorage $storage, TraceComparator $comparator): int
    {
        $id1 = (string) $this->argument('id1');
        $id2 = (string) $this->argument('id2');

        $before = $storage->retrieve($id1);
        if (! $before instanceof TraceData) {
            $this->error("Trace not found: {$id1}");

            return Command::FAILURE;
        }

        $after = $storage->retrieve($id2);
        if (! $after instanceof TraceData) {
            $this->error("Trace not found: {$id2}");

            return Command::FAILURE;
        }

        $threshold = $this->option('threshold');
        $diff = $comparator->compare($before, $after, is_numeric($threshold) ? (float) $threshold : 0.0);

        if ($this->option('json')) {
            $this->line((string) json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $this->info("Comparing {$id1} → {$id2}");

        if (! $diff['has_differences']) {
            $this->info('No differences found.');

            return Command::SUCCESS;
        }

        if ($diff['request']['changed']) {
            $this->line("Request: {$diff['request']['before']} → {$diff['request']['after']}");
        }

        if ($diff['status']['changed']) {
            $this->line("Status: {$diff['status']['before']} → {$diff['status']['after']}");
        }

        if ($diff['duration']['changed']) {
            $sign = $diff['duration']['delta'] > 0 ? '+' : '';
            $this->line(sprintf(
                'Duration: %.2fms → %.2fms (%s%.2fms)',
                $diff['duration']['before'],
                $diff['duration']['after'],
                $sign,
                $diff['duration']['delta']
            ));
        }

        foreach ($diff['http_added'] as $call) {
            $this->line("+ HTTP call added: {$call}");
        }

        foreach ($diff['http_removed'] as $call) {
            $this->line("- HTTP call removed: {$call}");
        }

        foreach ($diff['http_status_changes'] as $change) {
            $this->line("~ HTTP status changed: {$change['call']} {$change['before']} → {$change['after']}");
        }

        return Command::SUCCESS;
    }
}
