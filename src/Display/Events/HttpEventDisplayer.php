<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Display\Events;

use Grazulex\LaravelChronotrace\Display\AbstractEventDisplayer;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Illuminate\Console\Command;

/**
 * Displayer pour les événements HTTP
 */
class HttpEventDisplayer extends AbstractEventDisplayer
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function display(Command $command, TraceData $trace, array $options = []): void
    {
        $events = $this->getEventsByType($trace, 'http');

        if ($events === []) {
            return;
        }

        $command->warn('🌐 HTTP EVENTS');

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');
            $timestamp = $this->getTimestampFormatted($event);
            $url = $this->getStringValue($event, 'url', 'N/A');
            $method = $this->getStringValue($event, 'method', 'N/A');

            match ($type) {
                'request_sending' => $command->line("  📤 [{$timestamp}] HTTP Request: {$method} {$url}" .
                                   ($this->hasKey($event, 'body_size') ? ' (body: ' . $this->getStringValue($event, 'body_size', '0') . ' bytes)' : '')),
                'response_received' => $command->line("  📥 [{$timestamp}] HTTP Response: {$method} {$url} → " .
                                     $this->getStringValue($event, 'status', 'N/A') .
                                     ($this->hasKey($event, 'response_size') ? ' (' . $this->getStringValue($event, 'response_size', '0') . ' bytes)' : '')),
                'connection_failed' => $command->line("  ❌ [{$timestamp}] HTTP Connection Failed: {$method} {$url}"),
                default => $command->line("  ❓ [{$timestamp}] Unknown HTTP event: {$type}"),
            };
        }

        $command->newLine();
    }

    public function getEventType(): string
    {
        return 'http';
    }

    public function canHandle(string $eventType): bool
    {
        return $eventType === 'http';
    }

    public function getSummary(array $events): array
    {
        $requests = 0;
        $responses = 0;
        $failures = 0;
        $totalBytes = 0;

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');

            match ($type) {
                'request_sending' => $requests++,
                'response_received' => $responses++,
                'connection_failed' => $failures++,
                default => null, // Ignore les types inconnus
            };

            if ($this->hasKey($event, 'response_size')) {
                $size = $this->getStringValue($event, 'response_size', '0');
                $totalBytes += is_numeric($size) ? (int) $size : 0;
            }
        }

        return [
            'requests' => $requests,
            'responses' => $responses,
            'failures' => $failures,
            'success_rate' => $requests > 0 ? round(($responses / $requests) * 100, 1) : 0,
            'total_bytes' => $totalBytes,
        ];
    }
}
