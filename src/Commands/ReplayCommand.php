<?php

namespace Grazulex\LaravelChronotrace\Commands;

use Exception;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Console\Command;

class ReplayCommand extends Command
{
    protected $signature = 'chronotrace:replay {trace-id} 
                                        {--db : Show only database events}
                                        {--cache : Show only cache events}
                                        {--http : Show only HTTP events}
                                        {--jobs : Show only job events}
                                        {--format=table : Output format (table|json|raw)}';

    protected $description = 'Replay and display events from a stored trace';

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

            $this->displayTraceHeader($trace);
            $this->displayCapturedEvents($trace);
        } catch (Exception $e) {
            $this->error("Failed to replay trace: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Affiche les informations générales de la trace
     */
    private function displayTraceHeader(TraceData $trace): void
    {
        $this->info('=== TRACE INFORMATION ===');
        $this->line("🆔 Trace ID: {$trace->traceId}");
        $this->line("🕒 Timestamp: {$trace->timestamp}");
        $this->line("🌍 Environment: {$trace->environment}");
        $this->line("🔗 Request URL: {$trace->request->url}");
        $this->line("📊 Response Status: {$trace->response->status}");
        $this->line("⏱️  Duration: {$trace->response->duration}ms");
        $this->line('💾 Memory Usage: ' . number_format($trace->response->memoryUsage / 1024, 2) . ' KB');
        $this->newLine();
    }

    /**
     * Affiche les événements capturés par nos Event Listeners
     */
    private function displayCapturedEvents(TraceData $trace): void
    {
        $this->info('=== CAPTURED EVENTS ===');

        // Vérifier les options pour filtrer les événements
        $showDb = $this->option('db');
        $showCache = $this->option('cache');
        $showHttp = $this->option('http');
        $showJobs = $this->option('jobs');

        // Si aucune option spécifique, afficher tout
        $showAll = ! ($showDb || $showCache || $showHttp || $showJobs);

        if ($showAll || $showDb) {
            $this->displayDatabaseEvents($trace->database);
        }

        if ($showAll || $showCache) {
            $this->displayCacheEvents($trace->cache);
        }

        if ($showAll || $showHttp) {
            $this->displayHttpEvents($trace->http);
        }

        if ($showAll || $showJobs) {
            $this->displayJobEvents($trace->jobs);
        }

        // Afficher un résumé statistique
        if ($showAll) {
            $this->displayEventsSummary($trace);
        }
    }

    /**
     * Affiche les événements de base de données
     *
     * @param  array<mixed>  $databaseEvents
     */
    private function displayDatabaseEvents(array $databaseEvents): void
    {
        if ($databaseEvents === []) {
            return;
        }

        $this->warn('📊 DATABASE EVENTS');
        foreach ($databaseEvents as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');
            $timestamp = $this->getTimestampFormatted($event);

            match ($type) {
                'query' => $this->line("  🔍 [{$timestamp}] Query: " . $this->getStringValue($event, 'sql', 'N/A') .
                           ' (' . $this->getStringValue($event, 'time', '0') . 'ms on ' . $this->getStringValue($event, 'connection', 'N/A') . ')'),
                'transaction_begin' => $this->line("  🔄 [{$timestamp}] Transaction BEGIN on " . $this->getStringValue($event, 'connection', 'N/A')),
                'transaction_commit' => $this->line("  ✅ [{$timestamp}] Transaction COMMIT on " . $this->getStringValue($event, 'connection', 'N/A')),
                'transaction_rollback' => $this->line("  ❌ [{$timestamp}] Transaction ROLLBACK on " . $this->getStringValue($event, 'connection', 'N/A')),
                default => $this->line("  ❓ [{$timestamp}] Unknown database event: {$type}"),
            };
        }
        $this->newLine();
    }

    /**
     * Affiche les événements de cache
     *
     * @param  array<mixed>  $cacheEvents
     */
    private function displayCacheEvents(array $cacheEvents): void
    {
        if ($cacheEvents === []) {
            return;
        }

        $this->warn('🗄️  CACHE EVENTS');
        foreach ($cacheEvents as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');
            $timestamp = $this->getTimestampFormatted($event);
            $key = $this->getStringValue($event, 'key', 'N/A');
            $store = $this->getStringValue($event, 'store', 'default');

            match ($type) {
                'hit' => $this->line("  ✅ [{$timestamp}] Cache HIT: {$key} (store: {$store}, size: " .
                         $this->getStringValue($event, 'value_size', 'N/A') . ' bytes)'),
                'miss' => $this->line("  ❌ [{$timestamp}] Cache MISS: {$key} (store: {$store})"),
                'write' => $this->line("  💾 [{$timestamp}] Cache WRITE: {$key} (store: {$store})"),
                'forget' => $this->line("  🗑️  [{$timestamp}] Cache FORGET: {$key} (store: {$store})"),
                default => $this->line("  ❓ [{$timestamp}] Unknown cache event: {$type}"),
            };
        }
        $this->newLine();
    }

    /**
     * Affiche les événements HTTP
     *
     * @param  array<mixed>  $httpEvents
     */
    private function displayHttpEvents(array $httpEvents): void
    {
        if ($httpEvents === []) {
            return;
        }

        $this->warn('🌐 HTTP EVENTS');
        foreach ($httpEvents as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');
            $timestamp = $this->getTimestampFormatted($event);
            $url = $this->getStringValue($event, 'url', 'N/A');
            $method = $this->getStringValue($event, 'method', 'N/A');

            match ($type) {
                'request_sending' => $this->line("  📤 [{$timestamp}] HTTP Request: {$method} {$url}" .
                                   ($this->hasKey($event, 'body_size') ? ' (body: ' . $this->getStringValue($event, 'body_size', '0') . ' bytes)' : '')),
                'response_received' => $this->line("  📥 [{$timestamp}] HTTP Response: {$method} {$url} → " .
                                     $this->getStringValue($event, 'status', 'N/A') .
                                     ($this->hasKey($event, 'response_size') ? ' (' . $this->getStringValue($event, 'response_size', '0') . ' bytes)' : '')),
                'connection_failed' => $this->line("  ❌ [{$timestamp}] HTTP Connection Failed: {$method} {$url}"),
                default => $this->line("  ❓ [{$timestamp}] Unknown HTTP event: {$type}"),
            };
        }
        $this->newLine();
    }

    /**
     * Affiche les événements de jobs/queue
     *
     * @param  array<mixed>  $jobEvents
     */
    private function displayJobEvents(array $jobEvents): void
    {
        if ($jobEvents === []) {
            return;
        }

        $this->warn('⚙️  JOB EVENTS');
        foreach ($jobEvents as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');
            $timestamp = $this->getTimestampFormatted($event);
            $jobName = $this->getStringValue($event, 'job_name', 'N/A');
            $queue = $this->getStringValue($event, 'queue', 'default');
            $connection = $this->getStringValue($event, 'connection', 'N/A');

            match ($type) {
                'job_processing' => $this->line("  🔄 [{$timestamp}] Job STARTED: {$jobName} (queue: {$queue}, connection: {$connection})" .
                                  ($this->hasKey($event, 'attempts') ? ' - attempt #' . $this->getStringValue($event, 'attempts', '1') : '')),
                'job_processed' => $this->line("  ✅ [{$timestamp}] Job COMPLETED: {$jobName} (queue: {$queue}, connection: {$connection})"),
                'job_failed' => $this->line("  ❌ [{$timestamp}] Job FAILED: {$jobName} (queue: {$queue}, connection: {$connection})" .
                              ($this->hasKey($event, 'exception') ? ' - ' . $this->getStringValue($event, 'exception', '') : '')),
                default => $this->line("  ❓ [{$timestamp}] Unknown job event: {$type}"),
            };
        }
        $this->newLine();
    }

    /**
     * Affiche un résumé statistique des événements
     */
    private function displayEventsSummary(TraceData $trace): void
    {
        $dbCount = count($trace->database);
        $cacheCount = count($trace->cache);
        $httpCount = count($trace->http);
        $jobsCount = count($trace->jobs);
        $totalEvents = $dbCount + $cacheCount + $httpCount + $jobsCount;

        if ($totalEvents === 0) {
            $this->warn('🤷 No events captured in this trace.');

            return;
        }

        $this->warn('📈 EVENTS SUMMARY');
        $this->line("  📊 Database events: {$dbCount}");
        $this->line("  🗄️  Cache events: {$cacheCount}");
        $this->line("  🌐 HTTP events: {$httpCount}");
        $this->line("  ⚙️  Job events: {$jobsCount}");
        $this->line("  📝 Total events: {$totalEvents}");
        $this->newLine();
    }

    /**
     * Extrait une valeur string d'un array de manière sécurisée
     *
     * @param  array<mixed>  $array
     */
    private function getStringValue(array $array, string $key, string $default = ''): string
    {
        if (! isset($array[$key])) {
            return $default;
        }

        $value = $array[$key];
        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Vérifie si une clé existe dans l'array
     *
     * @param  array<mixed>  $array
     */
    private function hasKey(array $array, string $key): bool
    {
        return isset($array[$key]);
    }

    /**
     * Formate un timestamp de manière sécurisée
     *
     * @param  array<mixed>  $event
     */
    private function getTimestampFormatted(array $event): string
    {
        if (! isset($event['timestamp'])) {
            return 'N/A';
        }

        $timestamp = $event['timestamp'];
        if (is_numeric($timestamp)) {
            return date('H:i:s.v', (int) $timestamp);
        }

        return 'N/A';
    }
}
