<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Services;

use Grazulex\LaravelChronotrace\Models\TraceData;

/**
 * Compares two traces: the main request, plus the outgoing HTTP calls captured in each.
 */
class TraceComparator
{
    /**
     * @return array{
     *     request: array{before: string, after: string, changed: bool},
     *     status: array{before: int, after: int, changed: bool},
     *     duration: array{before: float, after: float, delta: float, changed: bool},
     *     http_added: array<int, string>,
     *     http_removed: array<int, string>,
     *     http_status_changes: array<int, array{call: string, before: int, after: int}>,
     *     has_differences: bool
     * }
     */
    public function compare(TraceData $before, TraceData $after, float $durationThreshold = 0.0): array
    {
        $beforeRequest = $before->request->method . ' ' . $before->request->url;
        $afterRequest = $after->request->method . ' ' . $after->request->url;

        $delta = $after->response->duration - $before->response->duration;
        $durationChanged = abs($delta) > max($durationThreshold, 0.0);

        $beforeCalls = $this->httpCalls($before);
        $afterCalls = $this->httpCalls($after);

        $added = [];
        $removed = [];
        $statusChanges = [];

        foreach ($afterCalls as $call => $status) {
            if (! array_key_exists($call, $beforeCalls)) {
                $added[] = $call;
            } elseif ($beforeCalls[$call] !== $status) {
                $statusChanges[] = ['call' => $call, 'before' => $beforeCalls[$call], 'after' => $status];
            }
        }

        foreach (array_keys($beforeCalls) as $call) {
            if (! array_key_exists($call, $afterCalls)) {
                $removed[] = $call;
            }
        }

        $requestChanged = $beforeRequest !== $afterRequest;
        $statusChanged = $before->response->status !== $after->response->status;

        return [
            'request' => [
                'before' => $beforeRequest,
                'after' => $afterRequest,
                'changed' => $requestChanged,
            ],
            'status' => [
                'before' => $before->response->status,
                'after' => $after->response->status,
                'changed' => $statusChanged,
            ],
            'duration' => [
                'before' => $before->response->duration,
                'after' => $after->response->duration,
                'delta' => $delta,
                'changed' => $durationChanged,
            ],
            'http_added' => $added,
            'http_removed' => $removed,
            'http_status_changes' => $statusChanges,
            'has_differences' => $requestChanged
                || $statusChanged
                || $durationChanged
                || $added !== []
                || $removed !== []
                || $statusChanges !== [],
        ];
    }

    /**
     * Outgoing HTTP calls keyed by "METHOD url" with their final status (0 when the connection failed).
     *
     * @return array<string, int>
     */
    private function httpCalls(TraceData $trace): array
    {
        $calls = [];

        foreach ($trace->http as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $type = $entry['type'] ?? null;
            if (! in_array($type, ['response_received', 'connection_failed'], true)) {
                continue;
            }

            $method = is_string($entry['method'] ?? null) ? $entry['method'] : '?';
            $url = is_string($entry['url'] ?? null) ? $entry['url'] : '?';

            $status = 0;
            if ($type === 'response_received' && is_numeric($entry['status'] ?? null)) {
                $status = (int) $entry['status'];
            }

            $calls[$method . ' ' . $url] = $status;
        }

        return $calls;
    }
}
