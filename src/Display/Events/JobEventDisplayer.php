<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Display\Events;

use Grazulex\LaravelChronotrace\Display\AbstractEventDisplayer;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Illuminate\Console\Command;

/**
 * Displayer pour les événements de jobs/queue
 */
class JobEventDisplayer extends AbstractEventDisplayer
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function display(Command $command, TraceData $trace, array $options = []): void
    {
        $events = $this->getEventsByType($trace, 'jobs');

        if ($events === []) {
            return;
        }

        $command->warn('⚙️  JOB EVENTS');

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');
            $timestamp = $this->getTimestampFormatted($event);
            $jobName = $this->getStringValue($event, 'job_name', 'N/A');
            $queue = $this->getStringValue($event, 'queue', 'default');
            $connection = $this->getStringValue($event, 'connection', 'N/A');

            match ($type) {
                'job_processing' => $command->line("  🔄 [{$timestamp}] Job STARTED: {$jobName} (queue: {$queue}, connection: {$connection})" .
                                  ($this->hasKey($event, 'attempts') ? ' - attempt #' . $this->getStringValue($event, 'attempts', '1') : '')),
                'job_processed' => $command->line("  ✅ [{$timestamp}] Job COMPLETED: {$jobName} (queue: {$queue}, connection: {$connection})"),
                'job_failed' => $command->line("  ❌ [{$timestamp}] Job FAILED: {$jobName} (queue: {$queue}, connection: {$connection})" .
                              ($this->hasKey($event, 'exception') ? ' - ' . $this->getStringValue($event, 'exception', '') : '')),
                default => $command->line("  ❓ [{$timestamp}] Unknown job event: {$type}"),
            };
        }

        $command->newLine();
    }

    public function getEventType(): string
    {
        return 'jobs';
    }

    public function canHandle(string $eventType): bool
    {
        return $eventType === 'jobs' || $eventType === 'queue';
    }

    public function getSummary(array $events): array
    {
        $started = 0;
        $completed = 0;
        $failed = 0;

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');

            match ($type) {
                'job_processing' => $started++,
                'job_processed' => $completed++,
                'job_failed' => $failed++,
                default => null, // Ignore les types inconnus
            };
        }

        $total = $started + $completed + $failed;
        $successRate = $total > 0 ? round((($completed) / $total) * 100, 1) : 0;

        return [
            'started' => $started,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $successRate,
        ];
    }
}
