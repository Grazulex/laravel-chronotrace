<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Models;

/**
 * Données de la réponse HTTP capturée
 */
readonly class TraceResponse
{
    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $cookies
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $content,
        public float $duration,
        public int $memoryUsage,
        public float $timestamp,
        public ?string $exception,
        public array $cookies,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'headers' => $this->headers,
            'content' => $this->content,
            'duration' => $this->duration,
            'memory_usage' => $this->memoryUsage,
            'timestamp' => $this->timestamp,
            'exception' => $this->exception,
            'cookies' => $this->cookies,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: is_numeric($data['status'] ?? 200) ? (int) $data['status'] : 200,
            headers: is_array($data['headers'] ?? []) ? $data['headers'] ?? [] : [],
            content: $data['content'] ?? '',
            duration: is_numeric($data['duration'] ?? 0.0) ? (float) $data['duration'] : 0.0,
            memoryUsage: is_numeric($data['memory_usage'] ?? 0) ? (int) $data['memory_usage'] : 0,
            timestamp: is_numeric($data['timestamp'] ?? 0.0) ? (float) $data['timestamp'] : 0.0,
            exception: isset($data['exception']) && is_string($data['exception']) ? $data['exception'] : null,
            cookies: is_array($data['cookies'] ?? []) ? $data['cookies'] ?? [] : [],
        );
    }
}
