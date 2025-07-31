<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Jobs;

use Grazulex\LaravelChronotrace\Models\TraceData;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Job asynchrone pour stocker les traces sans impacter les performances
 */
class StoreTraceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly TraceData $traceData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TraceStorage $storage): void
    {
        try {
            $storage->store($this->traceData);
        } catch (Throwable $exception) {
            // Log l'erreur mais ne pas faire échouer le job
            logger()->error('Failed to store ChronoTrace', [
                'trace_id' => $this->traceData->traceId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
