<?php

use Grazulex\LaravelChronotrace\Listeners\DatabaseEventListener;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Mockery;

it('captures database query events', function (): void {
    config(['chronotrace.capture.database' => true]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldReceive('addCapturedData')
        ->once()
        ->with('database', Mockery::on(fn ($data): bool => $data['type'] === 'query'
            && isset($data['sql'])
            && isset($data['time'])));

    // Créer le listener
    $listener = new DatabaseEventListener($mockRecorder);

    // Mock de connection
    $mockConnection = Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getName')->andReturn('mysql');

    // Simuler un événement de requête
    $event = new QueryExecuted(
        'SELECT * FROM users WHERE id = ?',
        [1],
        123.45,
        $mockConnection
    );

    // Exécuter le listener
    $listener->handleQueryExecuted($event);
});

it('does not capture database events when disabled', function (): void {
    config(['chronotrace.capture.database' => false]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldNotReceive('addCapturedData');

    $listener = new DatabaseEventListener($mockRecorder);

    // Mock de connection
    $mockConnection = Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getName')->andReturn('mysql');

    $event = new QueryExecuted(
        'SELECT * FROM users',
        [],
        50,
        $mockConnection
    );

    $listener->handleQueryExecuted($event);
});

it('captures transaction events', function (): void {
    config(['chronotrace.capture.database' => true]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldReceive('addCapturedData')
        ->times(2) // Pour begin et commit
        ->with('database', Mockery::on(fn ($data): bool => in_array($data['type'], ['transaction_begin', 'transaction_commit'])));

    $listener = new DatabaseEventListener($mockRecorder);

    // Mock de connection
    $mockConnection = Mockery::mock(Connection::class);
    $mockConnection->shouldReceive('getName')->andReturn('mysql');

    // Test transaction begin
    $beginEvent = new TransactionBeginning($mockConnection);
    $listener->handleTransactionBeginning($beginEvent);

    // Test transaction commit
    $commitEvent = new TransactionCommitted($mockConnection);
    $listener->handleTransactionCommitted($commitEvent);
});
