<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Listeners;

use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;

/**
 * Listener pour capturer les requêtes HTTP externes
 */
class HttpEventListener
{
    public function __construct(
        private readonly TraceRecorder $traceRecorder
    ) {}

    /**
     * Capture l'envoi de requêtes HTTP
     */
    public function handleRequestSending(RequestSending $event): void
    {
        if (! config('chronotrace.capture.http', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('http', [
            'type' => 'request_sending',
            'method' => $event->request->method(),
            'url' => $this->scrubUrl($event->request->url()),
            'headers' => $this->scrubHeaders($event->request->headers()),
            'body_size' => $this->getBodySize($event->request->body()),
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Capture la réception de réponses HTTP
     */
    public function handleResponseReceived(ResponseReceived $event): void
    {
        if (! config('chronotrace.capture.http', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('http', [
            'type' => 'response_received',
            'method' => $event->request->method(),
            'url' => $this->scrubUrl($event->request->url()),
            'status' => $event->response->status(),
            'response_size' => strlen($event->response->body()),
            'headers' => $this->scrubHeaders($event->response->headers()),
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Capture les échecs de connexion
     */
    public function handleConnectionFailed(ConnectionFailed $event): void
    {
        if (! config('chronotrace.capture.http', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('http', [
            'type' => 'connection_failed',
            'method' => $event->request->method(),
            'url' => $this->scrubUrl($event->request->url()),
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Nettoie l'URL pour masquer les données sensibles
     */
    private function scrubUrl(string $url): string
    {
        // Masquer les tokens dans les URLs
        $patterns = [
            '/([?&])token=([^&]+)/' => '$1token=[SCRUBBED]',
            '/([?&])api_key=([^&]+)/' => '$1api_key=[SCRUBBED]',
            '/([?&])access_token=([^&]+)/' => '$1access_token=[SCRUBBED]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $url);
            if ($result !== null) {
                $url = $result;
            }
        }

        return $url;
    }

    /**
     * Nettoie les headers sensibles
     *
     * @param  array<string, mixed>  $headers
     *
     * @return array<string, mixed>
     */
    private function scrubHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'x-auth-token',
            'cookie',
            'set-cookie',
        ];

        $scrubbed = [];
        foreach ($headers as $name => $value) {
            $lowerName = strtolower($name);
            $scrubbed[$name] = in_array($lowerName, $sensitiveHeaders, true) ? '[SCRUBBED]' : $value;
        }

        return $scrubbed;
    }

    /**
     * Calcule la taille du body de la requête
     */
    private function getBodySize(mixed $body): int
    {
        if (is_string($body)) {
            return strlen($body);
        }

        if (is_array($body)) {
            $encoded = json_encode($body);

            return $encoded !== false ? strlen($encoded) : 0;
        }

        return 0;
    }
}
