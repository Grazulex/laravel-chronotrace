<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Display\Events;

use Grazulex\LaravelChronotrace\Display\AbstractEventDisplayer;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Illuminate\Console\Command;

/**
 * Displayer pour les événements de cache
 */
class CacheEventDisplayer extends AbstractEventDisplayer
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function display(Command $command, TraceData $trace, array $options = []): void
    {
        $events = $this->getEventsByType($trace, 'cache');

        if ($events === []) {
            return;
        }

        $command->warn('🗄️  CACHE EVENTS');

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');
            $timestamp = $this->getTimestampFormatted($event);
            $key = $this->getStringValue($event, 'key', 'N/A');
            $store = $this->getStringValue($event, 'store', 'default');

            match ($type) {
                'hit' => $command->line("  ✅ [{$timestamp}] Cache HIT: {$key} (store: {$store}, size: " .
                         $this->getStringValue($event, 'value_size', 'N/A') . ' bytes)'),
                'miss' => $command->line("  ❌ [{$timestamp}] Cache MISS: {$key} (store: {$store})"),
                'write' => $command->line("  💾 [{$timestamp}] Cache WRITE: {$key} (store: {$store})"),
                'forget' => $command->line("  🗑️  [{$timestamp}] Cache FORGET: {$key} (store: {$store})"),
                default => $command->line("  ❓ [{$timestamp}] Unknown cache event: {$type}"),
            };
        }

        $command->newLine();
    }

    public function getEventType(): string
    {
        return 'cache';
    }

    public function canHandle(string $eventType): bool
    {
        return $eventType === 'cache';
    }

    public function getSummary(array $events): array
    {
        $hits = 0;
        $misses = 0;
        $writes = 0;
        $forgets = 0;

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');

            match ($type) {
                'hit' => $hits++,
                'miss' => $misses++,
                'write' => $writes++,
                'forget' => $forgets++,
                default => null, // Ignore les types inconnus
            };
        }

        $total = $hits + $misses;
        $hitRate = $total > 0 ? round(($hits / $total) * 100, 1) : 0;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'writes' => $writes,
            'forgets' => $forgets,
            'hit_rate' => $hitRate,
        ];
    }
}
