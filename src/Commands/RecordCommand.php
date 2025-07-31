<?php

namespace Grazulex\LaravelChronotrace\Commands;

use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Console\Command;

class RecordCommand extends Command
{
    protected $signature = 'chronotrace:record {url} {--method=GET} {--data=} {--timeout=30}';

    protected $description = 'Record a trace for a specific URL';

    public function handle(TraceRecorder $recorder): int
    {
        $url = $this->argument('url');
        $method = $this->option('method');
        if ($this->option('data')) {
            json_decode($this->option('data'), true);
        }
        $this->option('timeout');

        $this->info("Recording trace for {$method} {$url}...");

        // TODO: Implement HTTP request recording
        $this->warn('Recording functionality not yet implemented.');

        return Command::SUCCESS;
    }
}
