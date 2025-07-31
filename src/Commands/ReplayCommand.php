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
                                        {--format=table : Output format (table|json|raw)}
                                        {--generate-test : Generate a Pest test file}
                                        {--test-path=tests/Generated : Path for generated test files}';

    protected $description = 'Replay events from a stored trace or generate Pest tests';

    public function handle(TraceStorage $storage): int
    {
        $traceId = $this->argument('trace-id');
        $format = $this->option('format') ?: 'table';

        $this->info("Replaying trace {$traceId}...");

        try {
            $trace = $storage->retrieve($traceId);

            if (! $trace instanceof TraceData) {
                $this->error("Trace {$traceId} not found.");

                return Command::FAILURE;
            }

            if ($this->option('generate-test')) {
                $this->generatePestTest($trace);
            } elseif ($format === 'json') {
                $this->outputAsJson($trace);
            } elseif ($format === 'raw') {
                $this->outputAsRaw($trace);
            } else {
                $this->displayTraceHeader($trace);
                $this->displayCapturedEvents($trace);
            }
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

    /**
     * Génère un test Pest à partir d'une trace
     */
    private function generatePestTest(TraceData $trace): void
    {
        $this->info("Generating Pest test for trace {$trace->traceId}...");

        $testPath = $this->option('test-path') ?: 'tests/Generated';
        $testFile = $testPath . '/' . 'ChronoTrace_' . substr($trace->traceId, 0, 8) . '_Test.php';

        // Créer le dossier si nécessaire
        if (! is_dir($testPath)) {
            mkdir($testPath, 0755, true);
        }

        $testContent = $this->buildPestTestContent($trace);

        file_put_contents($testFile, $testContent);

        $this->info("✅ Pest test generated: {$testFile}");
        $this->line("Run with: ./vendor/bin/pest {$testFile}");
    }

    /**
     * Construit le contenu du test Pest
     */
    private function buildPestTestContent(TraceData $trace): string
    {
        $requestMethod = strtolower($trace->request->method); // Utiliser minuscules pour Pest
        $requestUrl = $trace->request->url;
        $responseStatus = $trace->response->status;
        $testName = "trace replay for {$trace->request->method} {$requestUrl}";

        $testContent = "<?php\n\n";
        $testContent .= "/**\n";
        $testContent .= " * Generated Pest test from ChronoTrace\n";
        $testContent .= " * Trace ID: {$trace->traceId}\n";
        $testContent .= ' * Generated at: ' . date('Y-m-d H:i:s') . "\n";
        $testContent .= " */\n\n";

        // Imports nécessaires
        $testContent .= "use Illuminate\\Foundation\\Testing\\RefreshDatabase;\n";
        $testContent .= "use Illuminate\\Support\\Facades\\Cache;\n";
        $testContent .= "use Illuminate\\Support\\Facades\\DB;\n\n";

        // Test principal
        $testContent .= "it('{$testName}', function () {\n";

        // Setup des données si POST/PUT/PATCH
        if (in_array(strtoupper($requestMethod), ['POST', 'PUT', 'PATCH']) && $trace->request->input !== []) {
            $testContent .= '    $requestData = ' . $this->formatPHPArray($trace->request->input) . ";\n\n";
        }

        // Extraire le path de l'URL complète
        $urlPath = parse_url($requestUrl, PHP_URL_PATH) ?: '/';
        $queryString = parse_url($requestUrl, PHP_URL_QUERY);
        if ($queryString) {
            $urlPath .= '?' . $queryString;
        }

        // Requête HTTP
        $testContent .= "    \$response = \$this->{$requestMethod}('{$urlPath}'";

        if (in_array(strtoupper($requestMethod), ['POST', 'PUT', 'PATCH']) && $trace->request->input !== []) {
            $testContent .= ', $requestData';
        }

        // Headers (filtrer et nettoyer)
        $cleanHeaders = $this->filterHeaders($trace->request->headers);
        if ($cleanHeaders !== []) {
            $testContent .= ', ' . $this->formatPHPArray($cleanHeaders);
        }

        $testContent .= ");\n\n";

        // Assertions de base
        $testContent .= "    \$response->assertStatus({$responseStatus});\n";

        // Assertions de structure de réponse
        if ($trace->response->content !== '' && $trace->response->content !== '0') {
            $responseData = json_decode($trace->response->content, true);

            if (is_array($responseData)) {
                $structure = $this->extractJsonStructure($responseData);
                $testContent .= '    $response->assertJsonStructure(' . $this->formatPHPArray($structure) . ");\n";
            }
        }

        // Assertions pour les headers de réponse importants (améliorer la détection)
        foreach ($trace->response->headers as $header => $value) {
            if (in_array(strtolower((string) $header), ['content-type', 'location', 'cache-control'])) {
                $headerValue = $this->getFirstHeaderValue($value);
                if ($headerValue !== 'unknown' && $headerValue !== '') {
                    $testContent .= "    \$response->assertHeader('{$header}', '{$headerValue}');\n";
                }
            }
        }

        // Assertions de base de données si on a des queries
        if ($trace->database !== []) {
            $testContent .= "\n    // Database assertions from captured queries\n";
            $queryCount = count($trace->database);
            $testContent .= "    \$this->assertTrue(DB::getQueryLog() !== [], 'Database queries should be executed');\n";
            $testContent .= "    // Expected approximately {$queryCount} queries\n";
        }

        // Assertions de cache si on a des opérations
        if ($trace->cache !== []) {
            $testContent .= "\n    // Cache assertions from captured operations\n";
            foreach ($trace->cache as $cacheOp) {
                if (is_array($cacheOp) && isset($cacheOp['type']) && $cacheOp['type'] === 'hit' && isset($cacheOp['key'])) {
                    $cacheKey = is_scalar($cacheOp['key']) ? (string) $cacheOp['key'] : 'unknown';
                    if ($cacheKey !== 'unknown') {
                        $testContent .= "    // Cache key '{$cacheKey}' should exist if reproduced\n";
                        $testContent .= "    // \$this->assertTrue(Cache::has('{$cacheKey}'));\n";
                    }
                }
            }
        }

        $testContent .= "})->uses(RefreshDatabase::class);\n\n";

        // Test de performance basé sur les métriques capturées
        if ($trace->response->duration > 0) {
            $maxDuration = $trace->response->duration * 2; // 2x la durée originale
            $testContent .= "it('performs within acceptable time limits', function () {\n";
            $testContent .= "    \$start = microtime(true);\n";
            $testContent .= "    \$this->{$requestMethod}('{$urlPath}');\n";
            $testContent .= "    \$duration = microtime(true) - \$start;\n";
            $testContent .= "    \$this->assertLessThan({$maxDuration}, \$duration, 'Request took too long');\n";
            $testContent .= "});\n\n";
        }

        // Test spécifique pour les erreurs si status >= 400
        if ($responseStatus >= 400) {
            $testContent .= "it('handles error response correctly', function () {\n";
            $testContent .= "    \$response = \$this->{$requestMethod}('{$urlPath}');\n";
            $testContent .= "    \$response->assertStatus({$responseStatus});\n";

            if ($trace->response->content !== '' && $trace->response->content !== '0') {
                $testContent .= "    \$response->assertJsonStructure(['message']); // Error responses should have message\n";
            }

            $testContent .= "});\n";
        }

        return $testContent;
    }

    /**
     * Sortie de la trace au format JSON
     */
    private function outputAsJson(TraceData $trace): void
    {
        $jsonData = [
            'trace_id' => $trace->traceId,
            'timestamp' => $trace->timestamp,
            'environment' => $trace->environment,
            'request' => [
                'method' => $trace->request->method,
                'url' => $trace->request->url,
                'headers' => $trace->request->headers,
                'query' => $trace->request->query,
                'input' => $trace->request->input,
                'files' => $trace->request->files,
                'user' => $trace->request->user,
                'session' => $trace->request->session,
                'user_agent' => $trace->request->userAgent,
                'ip' => $trace->request->ip,
            ],
            'response' => [
                'status' => $trace->response->status,
                'headers' => $trace->response->headers,
                'content' => $trace->response->content,
                'duration' => $trace->response->duration,
                'memory_usage' => $trace->response->memoryUsage,
                'exception' => $trace->response->exception,
                'cookies' => $trace->response->cookies,
            ],
            'context' => [
                'laravel_version' => $trace->context->laravel_version,
                'php_version' => $trace->context->php_version,
                'config' => $trace->context->config,
                'env_vars' => $trace->context->env_vars,
                'git_commit' => $trace->context->git_commit,
                'branch' => $trace->context->branch,
                'packages' => $trace->context->packages,
                'middlewares' => $trace->context->middlewares,
                'providers' => $trace->context->providers,
            ],
            'events' => [
                'database' => $trace->database,
                'cache' => $trace->cache,
                'http' => $trace->http,
                'mail' => $trace->mail,
                'notifications' => $trace->notifications,
                'events' => $trace->events,
                'jobs' => $trace->jobs,
                'filesystem' => $trace->filesystem,
            ],
        ];

        $json = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $this->error('Failed to encode trace as JSON');

            return;
        }

        $this->line($json);
    }

    /**
     * Sortie de la trace au format brut (sérialisé)
     */
    private function outputAsRaw(TraceData $trace): void
    {
        $serialized = serialize($trace);
        $this->line($serialized);
    }

    /**
     * Formate un tableau PHP avec une indentation propre
     *
     * @param  array<mixed>  $array
     */
    private function formatPHPArray(array $array): string
    {
        $export = var_export($array, true);
        // Améliorer le formatage
        $export = preg_replace('/^(\s+)/m', '    $1', $export);

        return $export ?: '';
    }

    /**
     * Filtre les headers pour garder seulement les utiles pour les tests
     *
     * @param  array<string, mixed>  $headers
     *
     * @return array<string, mixed>
     */
    private function filterHeaders(array $headers): array
    {
        $allowedHeaders = ['accept', 'content-type', 'authorization', 'x-requested-with'];
        $filtered = [];

        foreach ($headers as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (in_array($lowerKey, $allowedHeaders)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Extrait la structure JSON de manière récursive
     *
     * @param  array<mixed>  $data
     *
     * @return array<mixed>
     */
    private function extractJsonStructure(array $data): array
    {
        $structure = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $structure[$key] = $this->extractJsonStructure($value);
            } else {
                $structure[] = $key;
            }
        }

        return $structure;
    }

    /**
     * Récupère la première valeur d'un header
     */
    private function getFirstHeaderValue(mixed $value): string
    {
        if (is_array($value) && count($value) > 0) {
            $firstValue = $value[0];

            return is_scalar($firstValue) ? (string) $firstValue : 'unknown';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return 'unknown';
    }
}
