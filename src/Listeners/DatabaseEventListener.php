<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Listeners;

use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;

/**
 * Listener pour capturer les événements de base de données
 */
class DatabaseEventListener
{
    public function __construct(
        private readonly TraceRecorder $traceRecorder
    ) {}

    /**
     * Capture l'exécution des requêtes SQL
     */
    public function handleQueryExecuted(QueryExecuted $event): void
    {
        if (! config('chronotrace.capture.database', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('database', [
            'type' => 'query',
            'sql' => $event->sql,
            'bindings' => $event->bindings,
            'time' => $event->time,
            'connection' => $event->connectionName,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Capture le début des transactions
     */
    public function handleTransactionBeginning(TransactionBeginning $event): void
    {
        if (! config('chronotrace.capture.database', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('database', [
            'type' => 'transaction_begin',
            'connection' => $event->connectionName,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Capture les commits de transactions
     */
    public function handleTransactionCommitted(TransactionCommitted $event): void
    {
        if (! config('chronotrace.capture.database', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('database', [
            'type' => 'transaction_commit',
            'connection' => $event->connectionName,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Capture les rollbacks de transactions
     */
    public function handleTransactionRolledBack(TransactionRolledBack $event): void
    {
        if (! config('chronotrace.capture.database', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('database', [
            'type' => 'transaction_rollback',
            'connection' => $event->connectionName,
            'timestamp' => microtime(true),
        ]);
    }
}
