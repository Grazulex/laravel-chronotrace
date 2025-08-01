<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Display\Formatters;

use Grazulex\LaravelChronotrace\Contracts\OutputFormatterInterface;
use Grazulex\LaravelChronotrace\Models\TraceData;
use RuntimeException;

/**
 * Formatter pour la sortie JSON
 */
class JsonOutputFormatter implements OutputFormatterInterface
{
    public function format(TraceData $trace): string
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
            throw new RuntimeException('Failed to encode trace as JSON');
        }

        return $json;
    }

    public function getFormatType(): string
    {
        return 'json';
    }

    public function canHandle(string $formatType): bool
    {
        return $formatType === 'json';
    }
}
