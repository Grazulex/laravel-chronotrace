<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Display\Analysis;

/**
 * Detects SQL problems (N+1, duplicate and slow queries) in captured database events.
 */
class SqlAnalyzer
{
    public const DEFAULT_SLOW_THRESHOLD_MS = 100.0;

    public const DEFAULT_N_PLUS_ONE_THRESHOLD = 3;

    public function __construct(
        private readonly float $slowThresholdMs = self::DEFAULT_SLOW_THRESHOLD_MS,
        private readonly int $nPlusOneThreshold = self::DEFAULT_N_PLUS_ONE_THRESHOLD,
    ) {}

    /**
     * @param  array<mixed>  $events
     *
     * @return array{
     *     n_plus_one: list<array{sql: string, count: int, total_time: float}>,
     *     duplicates: list<array{sql: string, bindings: array<mixed>, count: int, total_time: float}>,
     *     slow: list<array{sql: string, time: float}>
     * }
     */
    public function analyze(array $events): array
    {
        /** @var array<string, array{sql: string, count: int, time: float, bindings: array<string, true>}> $patterns */
        $patterns = [];
        /** @var array<string, array{sql: string, bindings: array<mixed>, count: int, time: float}> $exact */
        $exact = [];
        $slow = [];

        foreach ($events as $event) {
            if (! is_array($event) || ($event['type'] ?? null) !== 'query') {
                continue;
            }

            $sql = $event['sql'] ?? null;
            if (! is_string($sql) || $sql === '') {
                continue;
            }

            $bindings = isset($event['bindings']) && is_array($event['bindings']) ? array_values($event['bindings']) : [];
            $time = isset($event['time']) && is_numeric($event['time']) ? (float) $event['time'] : 0.0;
            // Include the raw SQL so queries with inlined literals are told apart too.
            $bindingKey = json_encode($bindings) . '|' . strtolower(trim((string) preg_replace('/\s+/', ' ', $sql)));

            $pattern = $this->normalize($sql);
            $patterns[$pattern] ??= ['sql' => $sql, 'count' => 0, 'time' => 0.0, 'bindings' => []];
            $patterns[$pattern]['count']++;
            $patterns[$pattern]['time'] += $time;
            $patterns[$pattern]['bindings'][$bindingKey] = true;

            $exactKey = $pattern . '|' . $bindingKey;
            $exact[$exactKey] ??= ['sql' => $sql, 'bindings' => $bindings, 'count' => 0, 'time' => 0.0];
            $exact[$exactKey]['count']++;
            $exact[$exactKey]['time'] += $time;

            if ($time >= $this->slowThresholdMs) {
                $slow[] = ['sql' => $sql, 'time' => $time];
            }
        }

        $nPlusOne = [];
        foreach ($patterns as $data) {
            if ($data['count'] >= $this->nPlusOneThreshold && count($data['bindings']) > 1) {
                $nPlusOne[] = ['sql' => $data['sql'], 'count' => $data['count'], 'total_time' => $data['time']];
            }
        }

        $duplicates = [];
        foreach ($exact as $data) {
            if ($data['count'] > 1) {
                $duplicates[] = ['sql' => $data['sql'], 'bindings' => $data['bindings'], 'count' => $data['count'], 'total_time' => $data['time']];
            }
        }

        return [
            'n_plus_one' => $nPlusOne,
            'duplicates' => $duplicates,
            'slow' => $slow,
        ];
    }

    /**
     * Collapse literals so queries differing only by values share a pattern.
     */
    private function normalize(string $sql): string
    {
        $sql = (string) preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/", '?', $sql);
        $sql = (string) preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $sql);
        $sql = (string) preg_replace('/\(\s*\?(?:\s*,\s*\?)*\s*\)/', '(?)', $sql);

        return strtolower(trim((string) preg_replace('/\s+/', ' ', $sql)));
    }
}
