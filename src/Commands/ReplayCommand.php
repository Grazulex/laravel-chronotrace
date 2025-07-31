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
                                        {--test-path=tests/Generated : Path for generated test files}
                                        {--detailed : Show detailed information including context, headers, and response content}
                                        {--context : Show Laravel context (versions, config, env vars)}
                                        {--headers : Show request and response headers}
                                        {--content : Show response content}
                                        {--bindings : Show SQL query bindings}
                                        {--compact : Show minimal information only}';

    protected $description = 'Replay events from a stored trace or generate Pest tests. Use --detailed for detailed output, --context for Laravel info, --headers for HTTP details, --content for response body, --bindings for SQL parameters.';

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
                
                // Afficher le contexte si demandé
                if ($this->option('detailed') || $this->option('context')) {
                    $this->displayContext($trace);
                }
                
                // Afficher les détails de la requête si demandé
                if ($this->option('detailed') || $this->option('headers')) {
                    $this->displayRequestDetails($trace);
                }
                
                $this->displayCapturedEvents($trace);
                
                // Afficher les détails de la réponse si demandé
                if ($this->option('detailed') || $this->option('headers') || $this->option('content')) {
                    $this->displayResponseDetails($trace);
                }
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
        
        // Afficher les informations utilisateur si disponibles
        if ($trace->request->user !== null) {
            $this->line("👤 User: " . json_encode($trace->request->user));
        }
        
        // Afficher l'IP et User Agent
        if (!empty($trace->request->ip)) {
            $this->line("🌐 IP Address: {$trace->request->ip}");
        }
        if (!empty($trace->request->userAgent)) {
            $this->line("🖥️  User Agent: {$trace->request->userAgent}");
        }
        
        $this->newLine();
    }

    /**
     * Affiche le contexte Laravel détaillé
     */
    private function displayContext(TraceData $trace): void
    {
        $this->info('=== LARAVEL CONTEXT ===');
        
        // Versions
        if (!empty($trace->context->laravel_version)) {
            $this->line("🚀 Laravel Version: {$trace->context->laravel_version}");
        }
        if (!empty($trace->context->php_version)) {
            $this->line("🐘 PHP Version: {$trace->context->php_version}");
        }
        
        // Git information
        if (!empty($trace->context->git_commit)) {
            $this->line("📋 Git Commit: {$trace->context->git_commit}");
        }
        if (!empty($trace->context->branch)) {
            $this->line("🌿 Git Branch: {$trace->context->branch}");
        }
        
        // Configuration importante
        if (!empty($trace->context->config)) {
            $this->warn('⚙️  Configuration:');
            foreach ($trace->context->config as $key => $value) {
                $valueStr = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
                $this->line("   • {$key}: {$valueStr}");
            }
        }
        
        // Variables d'environnement importantes
        if (!empty($trace->context->env_vars)) {
            $this->warn('🌱 Environment Variables:');
            foreach ($trace->context->env_vars as $key => $value) {
                $valueStr = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
                $this->line("   • {$key}: {$valueStr}");
            }
        }
        
        // Packages installés
        if (!empty($trace->context->packages)) {
            $this->warn('📦 Installed Packages:');
            foreach ($trace->context->packages as $package => $version) {
                $this->line("   • {$package}: {$version}");
            }
        }
        
        // Middlewares
        if (!empty($trace->context->middlewares)) {
            $this->warn('🔒 Active Middlewares:');
            foreach ($trace->context->middlewares as $middleware) {
                $this->line("   • {$middleware}");
            }
        }
        
        // Service Providers
        if (!empty($trace->context->providers)) {
            $this->warn('🏗️  Service Providers:');
            foreach ($trace->context->providers as $provider) {
                $this->line("   • {$provider}");
            }
        }
        
        $this->newLine();
    }

    /**
     * Affiche les détails de la requête
     */
    private function displayRequestDetails(TraceData $trace): void
    {
        $this->info('=== REQUEST DETAILS ===');
        
        $this->line("📝 Method: {$trace->request->method}");
        $this->line("🔗 URL: {$trace->request->url}");
        
        // Query parameters
        if (!empty($trace->request->query)) {
            $this->warn('❓ Query Parameters:');
            foreach ($trace->request->query as $key => $value) {
                $valueStr = is_array($value) ? json_encode($value) : (string)$value;
                $this->line("   • {$key}: {$valueStr}");
            }
        }
        
        // Input data (POST/PUT body)
        if (!empty($trace->request->input)) {
            $this->warn('📥 Input Data:');
            foreach ($trace->request->input as $key => $value) {
                $valueStr = is_array($value) ? json_encode($value) : (string)$value;
                $this->line("   • {$key}: {$valueStr}");
            }
        }
        
        // Files uploaded
        if (!empty($trace->request->files)) {
            $this->warn('📁 Uploaded Files:');
            foreach ($trace->request->files as $key => $file) {
                $fileStr = is_array($file) ? json_encode($file) : (string)$file;
                $this->line("   • {$key}: {$fileStr}");
            }
        }
        
        // Session data
        if (!empty($trace->request->session)) {
            $this->warn('🔐 Session Data:');
            foreach ($trace->request->session as $key => $value) {
                $valueStr = $key === '_token' ? '[SCRUBBED]' : (is_array($value) ? json_encode($value) : (string)$value);
                $this->line("   • {$key}: {$valueStr}");
            }
        }
        
        // Headers
        if (!empty($trace->request->headers)) {
            $this->warn('📋 Request Headers:');
            foreach ($trace->request->headers as $key => $value) {
                $valueStr = is_array($value) ? implode(', ', $value) : (string)$value;
                $this->line("   • {$key}: {$valueStr}");
            }
        }
        
        $this->newLine();
    }

    /**
     * Affiche les détails de la réponse
     */
    private function displayResponseDetails(TraceData $trace): void
    {
        $this->info('=== RESPONSE DETAILS ===');
        
        $this->line("📊 Status: {$trace->response->status}");
        $this->line("⏱️  Duration: {$trace->response->duration}ms");
        $this->line('💾 Memory: ' . number_format($trace->response->memoryUsage / 1024, 2) . ' KB');
        
        // Headers de réponse
        if (($this->option('detailed') || $this->option('headers')) && !empty($trace->response->headers)) {
            $this->warn('📋 Response Headers:');
            foreach ($trace->response->headers as $key => $value) {
                $valueStr = is_array($value) ? implode(', ', $value) : (string)$value;
                $this->line("   • {$key}: {$valueStr}");
            }
        }
        
        // Cookies
        if (!empty($trace->response->cookies)) {
            $this->warn('🍪 Cookies Set:');
            foreach ($trace->response->cookies as $cookie) {
                $cookieStr = is_array($cookie) ? json_encode($cookie) : (string)$cookie;
                $this->line("   • {$cookieStr}");
            }
        }
        
        // Exception si présente
        if ($trace->response->exception !== null) {
            $this->error('❌ Exception:');
            $this->line("   {$trace->response->exception}");
        }
        
        // Contenu de la réponse
        if (($this->option('detailed') || $this->option('content')) && !empty($trace->response->content)) {
            $this->warn('📄 Response Content:');
            $content = $trace->response->content;
            
            // Limiter la taille d'affichage
            $maxLength = 1000;
            if (strlen($content) > $maxLength) {
                $content = substr($content, 0, $maxLength) . '... [TRUNCATED]';
            }
            
            // Essayer de formater le JSON si possible
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $formatted = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($formatted !== false) {
                    $content = $formatted;
                }
            }
            
            $this->line("   {$content}");
        }
        
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

        // Afficher les autres types d'événements si ils existent
        if ($showAll) {
            $this->displayMailEvents($trace->mail);
            $this->displayNotificationEvents($trace->notifications);
            $this->displayCustomEvents($trace->events);
            $this->displayFilesystemEvents($trace->filesystem);
        }

        // Afficher un résumé statistique
        if ($showAll && !$this->option('compact')) {
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
                'query' => $this->displayDatabaseQuery($event, $timestamp),
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
     * Affiche les détails d'une requête SQL
     *
     * @param  array<mixed>  $event
     */
    private function displayDatabaseQuery(array $event, string $timestamp): void
    {
        $sql = $this->getStringValue($event, 'sql', 'N/A');
        $time = $this->getStringValue($event, 'time', '0');
        $connection = $this->getStringValue($event, 'connection', 'N/A');
        
        $this->line("  🔍 [{$timestamp}] Query: {$sql} ({$time}ms on {$connection})");
        
        // Afficher les bindings si demandé et disponibles
        if (($this->option('detailed') || $this->option('bindings')) && isset($event['bindings']) && is_array($event['bindings']) && !empty($event['bindings'])) {
            $this->line("     📎 Bindings: " . json_encode($event['bindings']));
        }
    }

    /**
     * Affiche les événements de mail
     *
     * @param  array<mixed>  $mailEvents
     */
    private function displayMailEvents(array $mailEvents): void
    {
        if ($mailEvents === []) {
            return;
        }

        $this->warn('📧 MAIL EVENTS');
        foreach ($mailEvents as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');
            $timestamp = $this->getTimestampFormatted($event);
            $to = $this->getStringValue($event, 'to', 'N/A');
            $subject = $this->getStringValue($event, 'subject', 'N/A');

            match ($type) {
                'sending' => $this->line("  📤 [{$timestamp}] Sending email to: {$to} - Subject: {$subject}"),
                'sent' => $this->line("  ✅ [{$timestamp}] Email sent to: {$to} - Subject: {$subject}"),
                'failed' => $this->line("  ❌ [{$timestamp}] Email failed to: {$to} - Subject: {$subject}"),
                default => $this->line("  ❓ [{$timestamp}] Unknown mail event: {$type}"),
            };
        }
        $this->newLine();
    }

    /**
     * Affiche les événements de notification
     *
     * @param  array<mixed>  $notificationEvents
     */
    private function displayNotificationEvents(array $notificationEvents): void
    {
        if ($notificationEvents === []) {
            return;
        }

        $this->warn('🔔 NOTIFICATION EVENTS');
        foreach ($notificationEvents as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');
            $timestamp = $this->getTimestampFormatted($event);
            $channel = $this->getStringValue($event, 'channel', 'N/A');
            $notifiable = $this->getStringValue($event, 'notifiable', 'N/A');

            match ($type) {
                'sending' => $this->line("  📤 [{$timestamp}] Sending notification via {$channel} to: {$notifiable}"),
                'sent' => $this->line("  ✅ [{$timestamp}] Notification sent via {$channel} to: {$notifiable}"),
                'failed' => $this->line("  ❌ [{$timestamp}] Notification failed via {$channel} to: {$notifiable}"),
                default => $this->line("  ❓ [{$timestamp}] Unknown notification event: {$type}"),
            };
        }
        $this->newLine();
    }

    /**
     * Affiche les événements personnalisés Laravel
     *
     * @param  array<mixed>  $customEvents
     */
    private function displayCustomEvents(array $customEvents): void
    {
        if ($customEvents === []) {
            return;
        }

        $this->warn('🎯 CUSTOM EVENTS');
        foreach ($customEvents as $event) {
            if (! is_array($event)) {
                continue;
            }

            $eventName = $this->getStringValue($event, 'event', 'UnknownEvent');
            $timestamp = $this->getTimestampFormatted($event);
            $data = isset($event['data']) ? json_encode($event['data']) : 'N/A';

            $this->line("  🎯 [{$timestamp}] Event: {$eventName}");
            if ($this->option('detailed') && $data !== 'N/A') {
                $this->line("     📊 Data: {$data}");
            }
        }
        $this->newLine();
    }

    /**
     * Affiche les événements de système de fichiers
     *
     * @param  array<mixed>  $filesystemEvents
     */
    private function displayFilesystemEvents(array $filesystemEvents): void
    {
        if ($filesystemEvents === []) {
            return;
        }

        $this->warn('📁 FILESYSTEM EVENTS');
        foreach ($filesystemEvents as $event) {
            if (! is_array($event)) {
                continue;
            }

            $type = $this->getStringValue($event, 'type', 'unknown');
            $timestamp = $this->getTimestampFormatted($event);
            $path = $this->getStringValue($event, 'path', 'N/A');
            $disk = $this->getStringValue($event, 'disk', 'local');

            match ($type) {
                'read' => $this->line("  📖 [{$timestamp}] File READ: {$path} (disk: {$disk})"),
                'write' => $this->line("  ✏️  [{$timestamp}] File WRITE: {$path} (disk: {$disk})"),
                'delete' => $this->line("  🗑️  [{$timestamp}] File DELETE: {$path} (disk: {$disk})"),
                'copy' => $this->line("  📋 [{$timestamp}] File COPY: {$path} (disk: {$disk})"),
                'move' => $this->line("  📦 [{$timestamp}] File MOVE: {$path} (disk: {$disk})"),
                default => $this->line("  ❓ [{$timestamp}] Unknown filesystem event: {$type}"),
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
        $mailCount = count($trace->mail);
        $notificationCount = count($trace->notifications);
        $customEventCount = count($trace->events);
        $filesystemCount = count($trace->filesystem);
        $totalEvents = $dbCount + $cacheCount + $httpCount + $jobsCount + $mailCount + $notificationCount + $customEventCount + $filesystemCount;

        if ($totalEvents === 0) {
            $this->warn('🤷 No events captured in this trace.');
            return;
        }

        $this->warn('📈 EVENTS SUMMARY');
        $this->line("  📊 Database events: {$dbCount}");
        $this->line("  🗄️  Cache events: {$cacheCount}");
        $this->line("  🌐 HTTP events: {$httpCount}");
        $this->line("  ⚙️  Job events: {$jobsCount}");
        
        if ($mailCount > 0) {
            $this->line("  📧 Mail events: {$mailCount}");
        }
        if ($notificationCount > 0) {
            $this->line("  🔔 Notification events: {$notificationCount}");
        }
        if ($customEventCount > 0) {
            $this->line("  🎯 Custom events: {$customEventCount}");
        }
        if ($filesystemCount > 0) {
            $this->line("  📁 Filesystem events: {$filesystemCount}");
        }
        
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
