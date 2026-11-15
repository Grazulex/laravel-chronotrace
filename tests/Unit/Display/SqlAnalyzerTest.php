<?php

use Grazulex\LaravelChronotrace\Display\Analysis\SqlAnalyzer;

function sqlEvent(string $sql, array $bindings = [], float $time = 1.0): array
{
    return ['type' => 'query', 'sql' => $sql, 'bindings' => $bindings, 'time' => $time];
}

it('detects N+1 queries', function (): void {
    $events = [
        sqlEvent('select * from posts where user_id = ?', [1]),
        sqlEvent('select * from posts where user_id = ?', [2]),
        sqlEvent('select * from posts where user_id = ?', [3]),
    ];

    $result = (new SqlAnalyzer)->analyze($events);

    expect($result['n_plus_one'])->toHaveCount(1)
        ->and($result['n_plus_one'][0]['count'])->toBe(3)
        ->and($result['duplicates'])->toBe([]);
});

it('detects N+1 with inlined literals', function (): void {
    $events = [
        sqlEvent("select * from posts where user_id = 1 and kind = 'a'"),
        sqlEvent("select * from posts where user_id = 2 and kind = 'b'"),
        sqlEvent("select * from posts where user_id = 3 and kind = 'c'"),
    ];

    expect((new SqlAnalyzer)->analyze($events)['n_plus_one'])->toHaveCount(1);
});

it('detects duplicate queries', function (): void {
    $events = [
        sqlEvent('select * from users where id = ?', [5]),
        sqlEvent('select * from users where id = ?', [5]),
    ];

    $result = (new SqlAnalyzer)->analyze($events);

    expect($result['duplicates'])->toHaveCount(1)
        ->and($result['duplicates'][0]['count'])->toBe(2)
        ->and($result['n_plus_one'])->toBe([]);
});

it('detects slow queries using the threshold', function (): void {
    $events = [
        sqlEvent('select 1', [], 150.0),
        sqlEvent('select 2', [], 20.0),
    ];

    expect((new SqlAnalyzer)->analyze($events)['slow'])->toHaveCount(1);
    expect((new SqlAnalyzer(10.0))->analyze($events)['slow'])->toHaveCount(2);
});

it('ignores non query events and reports nothing for clean traces', function (): void {
    $events = [
        ['type' => 'transaction_begin'],
        'garbage',
        sqlEvent('select * from users where id = ?', [1]),
        sqlEvent('select * from posts where id = ?', [1]),
    ];

    expect((new SqlAnalyzer)->analyze($events))->toBe([
        'n_plus_one' => [],
        'duplicates' => [],
        'slow' => [],
    ]);
});
