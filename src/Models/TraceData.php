<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Models;

/**
 * Structure de données pour une trace ChronoTrace
 */
class TraceData
{
    /**
     * @param  array<string, mixed>  $database
     * @param  array<string, mixed>  $cache
     * @param  array<string, mixed>  $http
     * @param  array<string, mixed>  $mail
     * @param  array<string, mixed>  $notifications
     * @param  array<string, mixed>  $events
     * @param  array<string, mixed>  $jobs
     * @param  array<string, mixed>  $filesystem
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $traceId,
        public readonly string $timestamp,
        public readonly string $environment,
        public readonly TraceRequest $request,
        public readonly TraceResponse $response,
        public readonly TraceContext $context,
        public readonly array $database = [],
        public readonly array $cache = [],
        public readonly array $http = [],
        public readonly array $mail = [],
        public readonly array $notifications = [],
        public readonly array $events = [],
        public readonly array $jobs = [],
        public readonly array $filesystem = [],
        public readonly array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'trace_id' => $this->traceId,
            'timestamp' => $this->timestamp,
            'environment' => $this->environment,
            'request' => $this->request->toArray(),
            'response' => $this->response->toArray(),
            'context' => $this->context->toArray(),
            'database' => $this->database,
            'cache' => $this->cache,
            'http' => $this->http,
            'mail' => $this->mail,
            'notifications' => $this->notifications,
            'events' => $this->events,
            'jobs' => $this->jobs,
            'filesystem' => $this->filesystem,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $requestData = $data['request'] ?? [];
        $responseData = $data['response'] ?? [];
        $contextData = $data['context'] ?? [];

        return new self(
            traceId: $data['trace_id'],
            timestamp: $data['timestamp'],
            environment: $data['environment'],
            request: TraceRequest::fromArray(is_array($requestData) ? $requestData : []),
            response: TraceResponse::fromArray(is_array($responseData) ? $responseData : []),
            context: TraceContext::fromArray(is_array($contextData) ? $contextData : []),
            database: is_array($data['database'] ?? []) ? $data['database'] ?? [] : [],
            cache: is_array($data['cache'] ?? []) ? $data['cache'] ?? [] : [],
            http: is_array($data['http'] ?? []) ? $data['http'] ?? [] : [],
            mail: is_array($data['mail'] ?? []) ? $data['mail'] ?? [] : [],
            notifications: is_array($data['notifications'] ?? []) ? $data['notifications'] ?? [] : [],
            events: is_array($data['events'] ?? []) ? $data['events'] ?? [] : [],
            jobs: is_array($data['jobs'] ?? []) ? $data['jobs'] ?? [] : [],
            filesystem: is_array($data['filesystem'] ?? []) ? $data['filesystem'] ?? [] : [],
            metadata: is_array($data['metadata'] ?? []) ? $data['metadata'] ?? [] : [],
        );
    }
}
