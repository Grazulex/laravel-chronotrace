<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Display\Events;

use Grazulex\LaravelChronotrace\Display\AbstractEventDisplayer;
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
