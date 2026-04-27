<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Commands;

use Exception;
use Grazulex\LaravelChronotrace\Display\DisplayManager;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Console\Command;

/**
 * Commande simplifiée pour rejouer des traces
 * Utilise l'architecture modulaire avec DisplayManager
 */
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

    protected $description = 'Replay events from a stored trace or generate Pest tests. Uses modular architecture with specialized displayers.';

    public function handle(TraceStorage $storage, DisplayManager $displayManager): int
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
                $testPath = $this->option('test-path') ?: 'tests/Generated';
                $testFile = $displayManager->generateTest($trace, 'pest', $testPath);
                $this->info("✅ Pest test generated: {$testFile}");
                $this->line("Run with: ./vendor/bin/pest {$testFile}");
            } elseif ($format === 'json' || $format === 'raw') {
                $output = $displayManager->formatOutput($trace, $format);
                $this->line($output);
            } else {
                // Affichage CLI standard
                $this->displayTraceHeader($trace);

                // Afficher le contexte si demandé
                if ($this->option('detailed') || $this->option('context')) {
                    $this->displayContext($trace);
                }

                // Afficher les détails de la requête si demandé
                if ($this->option('detailed') || $this->option('headers')) {
                    $this->displayRequestDetails($trace);
                }

                // Utiliser le DisplayManager pour afficher les événements
                $this->info('=== CAPTURED EVENTS ===');
                $options = [
                    'db' => $this->option('db'),
                    'cache' => $this->option('cache'),
                    'http' => $this->option('http'),
                    'jobs' => $this->option('jobs'),
                    'detailed' => $this->option('detailed'),
                    'bindings' => $this->option('bindings'),
                    'compact' => $this->option('compact'),
                ];

                $displayManager->displayEvents($this, $trace, $options);

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
            $this->line('👤 User: ' . json_encode($trace->request->user));
        }

        // Afficher l'IP et User Agent
        if ($trace->request->ip !== '' && $trace->request->ip !== '0') {
            $this->line("🌐 IP Address: {$trace->request->ip}");
        }
        if ($trace->request->userAgent !== '' && $trace->request->userAgent !== '0') {
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
        if ($trace->context->laravel_version !== '' && $trace->context->laravel_version !== '0') {
            $this->line("🚀 Laravel Version: {$trace->context->laravel_version}");
        }
        if ($trace->context->php_version !== '' && $trace->context->php_version !== '0') {
            $this->line("🐘 PHP Version: {$trace->context->php_version}");
        }

        // Git information
        if ($trace->context->git_commit !== '' && $trace->context->git_commit !== '0') {
            $this->line("📋 Git Commit: {$trace->context->git_commit}");
        }
        if ($trace->context->branch !== '' && $trace->context->branch !== '0') {
            $this->line("🌿 Git Branch: {$trace->context->branch}");
        }

        // Configuration importante
        if ($trace->context->config !== []) {
            $this->warn('⚙️  Configuration:');
            foreach ($trace->context->config as $key => $value) {
                $valueStr = $this->formatValue($value);
                $this->line("   • {$key}: {$valueStr}");
            }
        }

        // Variables d'environnement importantes
        if ($trace->context->env_vars !== []) {
            $this->warn('🌱 Environment Variables:');
            foreach ($trace->context->env_vars as $key => $value) {
                $valueStr = $this->formatValue($value);
                $this->line("   • {$key}: {$valueStr}");
            }
        }

        // Packages installés
        if ($trace->context->packages !== []) {
            $this->warn('📦 Installed Packages:');
            foreach ($trace->context->packages as $package => $version) {
                $this->line("   • {$package}: {$version}");
            }
        }

        // Middlewares
        if ($trace->context->middlewares !== []) {
            $this->warn('🔒 Active Middlewares:');
            foreach ($trace->context->middlewares as $middleware) {
                $this->line("   • {$middleware}");
            }
        }

        // Service Providers
        if ($trace->context->providers !== []) {
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
        if ($trace->request->query !== []) {
            $this->warn('❓ Query Parameters:');
            foreach ($trace->request->query as $key => $value) {
                $valueStr = is_array($value) ? json_encode($value) : $this->formatValue($value);
                $this->line("   • {$key}: {$valueStr}");
            }
        }

        // Input data (POST/PUT body)
        if ($trace->request->input !== []) {
            $this->warn('📥 Input Data:');
            foreach ($trace->request->input as $key => $value) {
                $valueStr = is_array($value) ? json_encode($value) : $this->formatValue($value);
                $this->line("   • {$key}: {$valueStr}");
            }
        }

        // Headers
        if ($trace->request->headers !== []) {
            $this->warn('📋 Request Headers:');
            foreach ($trace->request->headers as $key => $value) {
                $valueStr = is_array($value) ? implode(', ', array_map('strval', $value)) : $this->formatValue($value);
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
        if (($this->option('detailed') || $this->option('headers')) && $trace->response->headers !== []) {
            $this->warn('📋 Response Headers:');
            foreach ($trace->response->headers as $key => $value) {
                $valueStr = is_array($value) ? implode(', ', array_map('strval', $value)) : $this->formatValue($value);
                $this->line("   • {$key}: {$valueStr}");
            }
        }

        // Exception si présente
        if ($trace->response->exception !== null) {
            $this->error('❌ Exception:');
            $this->line("   {$trace->response->exception}");
        }

        // Contenu de la réponse
        if (($this->option('detailed') || $this->option('content')) && ($trace->response->content !== '' && $trace->response->content !== '0')) {
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
     * Formate une valeur de manière sécurisée pour l'affichage
     */
    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_object($value)) {
            return $value::class;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return 'unknown';
    }
}
