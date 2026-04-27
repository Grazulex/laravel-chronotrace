<?php

namespace Grazulex\LaravelChronotrace\Commands;

use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordCommand extends Command
{
    protected $signature = 'chronotrace:record {url} {--method=GET} {--data=} {--headers=} {--timeout=30}';

    protected $description = 'Record a trace for a specific URL by making an HTTP request';

    public function handle(TraceRecorder $recorder): int
    {
        $url = $this->argument('url');
        $method = strtoupper($this->option('method') ?? 'GET');
        $timeout = (int) $this->option('timeout');

        // Parse les données JSON si fournies
        $data = [];
        $dataOption = $this->option('data');
        if ($dataOption !== null) {
            $dataJson = json_decode((string) $dataOption, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON in --data option');

                return Command::FAILURE;
            }
            if (is_array($dataJson)) {
                /** @var array<string, mixed> $data */
                $data = $dataJson;
            }
        }

        // Parse les headers JSON si fournis
        $headers = [];
        $headersOption = $this->option('headers');
        if ($headersOption !== null) {
            $headersJson = json_decode((string) $headersOption, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON in --headers option');

                return Command::FAILURE;
            }
            if (is_array($headersJson)) {
                $headers = $headersJson;
            }
        }

        $this->info("Recording trace for {$method} {$url}...");

        try {
            // Créer une requête simulée pour le TraceRecorder
            $content = $data === [] ? null : json_encode($data);
            if ($content === false) {
                $content = null;
            }
            $request = Request::create($url, $method, $data, [], [], [], $content);

            // Ajouter les headers de manière sécurisée
            foreach ($headers as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $request->headers->set($key, $value);
                }
            }

            // Démarrer la capture
            $traceId = $recorder->startCapture($request);
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);

            // Faire la requête HTTP
            $httpClient = Http::timeout($timeout);
            if ($headers !== []) {
                $headerStrings = [];
                foreach ($headers as $key => $value) {
                    if (is_string($key) && is_string($value)) {
                        $headerStrings[$key] = $value;
                    }
                }
                $httpClient = $httpClient->withHeaders($headerStrings);
            }

            $httpResponse = match ($method) {
                'GET' => $httpClient->get($url),
                'POST' => $httpClient->post($url, $data),
                'PUT' => $httpClient->put($url, $data),
                'PATCH' => $httpClient->patch($url, $data),
                'DELETE' => $httpClient->delete($url),
                default => $httpClient->get($url)
            };

            // Calculer les métriques
            $duration = microtime(true) - $startTime;
            $memoryUsage = memory_get_usage(true) - $startMemory;

            // Créer une réponse Symfony compatible
            $response = new Response(
                $httpResponse->body(),
                $httpResponse->status(),
                $httpResponse->headers()
            );

            // Finaliser la capture
            $recorder->finishCapture($traceId, $response, $duration, $memoryUsage);

            $this->info('✅ Trace recorded successfully!');
            $this->line("📊 Status: {$httpResponse->status()}");
            $this->line('⏱️  Duration: ' . number_format($duration * 1000, 2) . 'ms');
            $this->line('💾 Memory: ' . number_format($memoryUsage / 1024, 2) . 'KB');
            $this->line("🆔 Trace ID: {$traceId}");

            if ($httpResponse->failed()) {
                $this->warn("⚠️  HTTP request failed with status {$httpResponse->status()}");
            }
        } catch (Throwable $e) {
            // En cas d'exception, capturer avec l'erreur si on a un traceId
            if (isset($traceId) && isset($startTime) && isset($startMemory)) {
                $duration = microtime(true) - $startTime;
                $memoryUsage = memory_get_usage(true) - $startMemory;
                $recorder->finishCaptureWithException($traceId, $e, $duration, $memoryUsage);
            }

            $this->error("❌ Failed to record trace: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
