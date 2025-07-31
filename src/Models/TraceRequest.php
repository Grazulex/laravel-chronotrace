<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Models;

/**
 * Données de la requête HTTP capturée
 */
readonly class TraceRequest
{
    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>|null  $user
     * @param  array<string, mixed>  $session
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers,
        public array $query,
        public array $input,
        public array $files,
        public ?array $user,
        public array $session,
        public string $userAgent,
        public string $ip,
        public float $timestamp,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'url' => $this->url,
            'headers' => $this->headers,
            'query' => $this->query,
            'input' => $this->input,
            'files' => $this->files,
            'user' => $this->user,
            'session' => $this->session,
            'user_agent' => $this->userAgent,
            'ip' => $this->ip,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: $data['method'] ?? '',
            url: $data['url'] ?? '',
            headers: is_array($data['headers'] ?? []) ? $data['headers'] ?? [] : [],
            query: is_array($data['query'] ?? []) ? $data['query'] ?? [] : [],
            input: is_array($data['input'] ?? []) ? $data['input'] ?? [] : [],
            files: is_array($data['files'] ?? []) ? $data['files'] ?? [] : [],
            user: isset($data['user']) && is_array($data['user']) ? $data['user'] : null,
            session: is_array($data['session'] ?? []) ? $data['session'] ?? [] : [],
            userAgent: $data['user_agent'] ?? '',
            ip: $data['ip'] ?? '',
            timestamp: is_numeric($data['timestamp'] ?? 0) ? (float) $data['timestamp'] : 0.0,
        );
    }
}
