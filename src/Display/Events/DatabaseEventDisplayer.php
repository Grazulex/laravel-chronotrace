<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Display\Events;

use Grazulex\LaravelChronotrace\Display\AbstractEventDisplayer;
use Grazulex\LaravelChronotrace\Display\Analysis\SqlAnalyzer;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Illuminate\Console\Command;

/**
 * Displayer pour les événements de base de données
 */
class DatabaseEventDisplayer extends AbstractEventDisplayer
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function display(Command $command, TraceData $trace, array $options = []): void
    {
        $events = $this->getEventsByType($trace, 'database');

        if ($events === []) {
            return;
        }

        $command->warn('📊 DATABASE EVENTS');

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');
            $timestamp = $this->getTimestampFormatted($event);

            match ($type) {
                'query' => $this->displayDatabaseQuery($command, $event, $timestamp, $options),
                'transaction_begin' => $command->line("  🔄 [{$timestamp}] Transaction BEGIN on " . $this->getStringValue($event, 'connection', 'N/A')),
                'transaction_commit' => $command->line("  ✅ [{$timestamp}] Transaction COMMIT on " . $this->getStringValue($event, 'connection', 'N/A')),
                'transaction_rollback' => $command->line("  ❌ [{$timestamp}] Transaction ROLLBACK on " . $this->getStringValue($event, 'connection', 'N/A')),
                default => $command->line("  ❓ [{$timestamp}] Unknown database event: {$type}"),
            };
        }

        $command->newLine();

        if (! empty($options['analyze'])) {
            $this->displayAnalysis($command, $events, $options);
        }
    }

    /**
     * @param  array<mixed>  $events
     * @param  array<string, mixed>  $options
     */
    private function displayAnalysis(Command $command, array $events, array $options): void
    {
        $threshold = isset($options['slow_threshold']) && is_numeric($options['slow_threshold'])
            ? (float) $options['slow_threshold']
            : SqlAnalyzer::DEFAULT_SLOW_THRESHOLD_MS;

        $result = (new SqlAnalyzer($threshold))->analyze($events);

        $command->warn('🧪 SQL ANALYSIS');

        if ($result['n_plus_one'] === [] && $result['duplicates'] === [] && $result['slow'] === []) {
            $command->line('  ✅ No N+1, duplicate or slow queries detected');
            $command->newLine();

            return;
        }

        foreach ($result['n_plus_one'] as $issue) {
            $command->line("  ⚠️  N+1: {$issue['sql']} (run {$issue['count']} times, {$issue['total_time']}ms total)");
        }

        foreach ($result['duplicates'] as $issue) {
            $command->line("  ♻️  Duplicate: {$issue['sql']} (run {$issue['count']} times with identical bindings, {$issue['total_time']}ms total)");
        }

        foreach ($result['slow'] as $issue) {
            $command->line("  🐢 Slow: {$issue['sql']} ({$issue['time']}ms, threshold {$threshold}ms)");
        }

        $command->newLine();
    }

    public function getEventType(): string
    {
        return 'database';
    }

    public function canHandle(string $eventType): bool
    {
        return $eventType === 'database' || $eventType === 'db';
    }

    public function getSummary(array $events): array
    {
        $queryCount = 0;
        $transactionCount = 0;
        $totalTime = 0;

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');

            if ($type === 'query') {
                $queryCount++;
                $time = $this->getStringValue($event, 'time', '0');
                $totalTime += is_numeric($time) ? (float) $time : 0;
            } elseif (str_contains($type, 'transaction')) {
                $transactionCount++;
            }
        }

        return [
            'queries' => $queryCount,
            'transactions' => $transactionCount,
            'total_time' => $totalTime,
            'avg_time' => $queryCount > 0 ? round($totalTime / $queryCount, 3) : 0,
        ];
    }

    /**
     * Affiche les détails d'une requête SQL
     *
     * @param  array<mixed>  $event
     * @param  array<string, mixed>  $options
     */
    private function displayDatabaseQuery(Command $command, array $event, string $timestamp, array $options): void
    {
        $sql = $this->getStringValue($event, 'sql', 'N/A');
        $time = $this->getStringValue($event, 'time', '0');
        $connection = $this->getStringValue($event, 'connection', 'N/A');

        $command->line("  🔍 [{$timestamp}] Query: {$sql} ({$time}ms on {$connection})");

        // Afficher les bindings si demandé et disponibles
        if (($this->shouldShowDetailed($options) || $this->shouldShowBindings($options)) &&
            isset($event['bindings']) && is_array($event['bindings']) && $event['bindings'] !== []) {
            $command->line('     📎 Bindings: ' . json_encode($event['bindings']));
        }
    }
}
