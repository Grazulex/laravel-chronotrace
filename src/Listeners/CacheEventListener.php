<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Listeners;

use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;

/**
 * Listener pour capturer les événements de cache
 */
class CacheEventListener
{
    public function __construct(
        private readonly TraceRecorder $traceRecorder
    ) {}

    /**
     * Capture les hits de cache
     */
    public function handleCacheHit(CacheHit $event): void
    {
        if (! config('chronotrace.capture.cache', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('cache', [
            'type' => 'hit',
            'key' => $this->scrubCacheKey($event->key),
            'value_size' => $this->getValueSize($event->value),
            'store' => $event->storeName ?? 'default',
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Capture les miss de cache
     */
    public function handleCacheMissed(CacheMissed $event): void
    {
        if (! config('chronotrace.capture.cache', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('cache', [
            'type' => 'miss',
            'key' => $this->scrubCacheKey($event->key),
            'store' => $event->storeName ?? 'default',
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Capture les écritures de cache
     */
    public function handleKeyWritten(KeyWritten $event): void
    {
        if (! config('chronotrace.capture.cache', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('cache', [
            'type' => 'write',
            'key' => $this->scrubCacheKey($event->key),
            'value_size' => $this->getValueSize($event->value),
            'store' => $event->storeName ?? 'default',
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Capture les suppressions de cache
     */
    public function handleKeyForgotten(KeyForgotten $event): void
    {
        if (! config('chronotrace.capture.cache', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('cache', [
            'type' => 'forget',
            'key' => $this->scrubCacheKey($event->key),
            'store' => $event->storeName ?? 'default',
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Nettoie les clés de cache sensibles
     */
    private function scrubCacheKey(string $key): string
    {
        // Masquer les clés contenant des données sensibles
        $sensitivePatterns = [
            '/user_\d+_token/',
            '/session_[a-f0-9]{40}/',
            '/auth_[a-f0-9]+/',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $key)) {
                return '[SCRUBBED_CACHE_KEY]';
            }
        }

        return $key;
    }

    /**
     * Calcule la taille approximative d'une valeur
     */
    private function getValueSize(mixed $value): int
    {
        if (is_string($value)) {
            return strlen($value);
        }

        if (is_array($value) || is_object($value)) {
            $serialized = serialize($value);

            return strlen($serialized);
        }

        if (is_scalar($value)) {
            return strlen((string) $value);
        }

        return 0;
    }
}
