<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Listeners;

use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

/**
 * Listener pour capturer les événements de queue et jobs
 */
class QueueEventListener
{
    public function __construct(
        private readonly TraceRecorder $traceRecorder
    ) {}

    /**
     * Capture le début de traitement d'un job
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        if (! config('chronotrace.capture.jobs', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('jobs', [
            'type' => 'job_processing',
            'job_name' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'attempts' => $event->job->attempts(),
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Capture la fin de traitement d'un job
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        if (! config('chronotrace.capture.jobs', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('jobs', [
            'type' => 'job_processed',
            'job_name' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Capture les échecs de jobs
     */
    public function handleJobFailed(JobFailed $event): void
    {
        if (! config('chronotrace.capture.jobs', true)) {
            return;
        }

        $this->traceRecorder->addCapturedData('jobs', [
            'type' => 'job_failed',
            'job_name' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'exception' => $event->exception->getMessage(),
            'timestamp' => microtime(true),
        ]);
    }
}
