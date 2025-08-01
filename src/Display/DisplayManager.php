<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Display;

use Grazulex\LaravelChronotrace\Contracts\EventDisplayerInterface;
use Grazulex\LaravelChronotrace\Contracts\OutputFormatterInterface;
use Grazulex\LaravelChronotrace\Contracts\TestGeneratorInterface;
use Grazulex\LaravelChronotrace\Display\Events\CacheEventDisplayer;
use Grazulex\LaravelChronotrace\Display\Events\DatabaseEventDisplayer;
use Grazulex\LaravelChronotrace\Display\Events\HttpEventDisplayer;
use Grazulex\LaravelChronotrace\Display\Events\JobEventDisplayer;
use Grazulex\LaravelChronotrace\Display\Formatters\JsonOutputFormatter;
use Grazulex\LaravelChronotrace\Display\Formatters\RawOutputFormatter;
use Grazulex\LaravelChronotrace\Display\TestGenerators\PestTestGenerator;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Manager centralisé pour la gestion des displayers, formatters et générateurs
 */
class DisplayManager
{
    /** @var array<EventDisplayerInterface> */
    private array $eventDisplayers = [];

    /** @var array<OutputFormatterInterface> */
    private array $outputFormatters = [];

    /** @var array<TestGeneratorInterface> */
    private array $testGenerators = [];

    public function __construct(
        DatabaseEventDisplayer $databaseDisplayer,
        CacheEventDisplayer $cacheDisplayer,
        HttpEventDisplayer $httpDisplayer,
        JobEventDisplayer $jobDisplayer,
        JsonOutputFormatter $jsonFormatter,
        RawOutputFormatter $rawFormatter,
        PestTestGenerator $pestGenerator
    ) {
        $this->eventDisplayers = [
            $databaseDisplayer,
            $cacheDisplayer,
            $httpDisplayer,
            $jobDisplayer,
        ];

        $this->outputFormatters = [
            'json' => $jsonFormatter,
            'raw' => $rawFormatter,
        ];

        $this->testGenerators = [
            'pest' => $pestGenerator,
        ];
    }

    /**
     * Affiche les événements dans la console selon les options
     *
     * @param  array<string, mixed>  $options
     */
    public function displayEvents(Command $command, TraceData $trace, array $options = []): void
    {
        // Vérifier les options pour filtrer les événements
        $showDb = $options['db'] ?? false;
        $showCache = $options['cache'] ?? false;
        $showHttp = $options['http'] ?? false;
        $showJobs = $options['jobs'] ?? false;

        // Si aucune option spécifique, afficher tout
        $showAll = ! ($showDb || $showCache || $showHttp || $showJobs);

        foreach ($this->eventDisplayers as $displayer) {
            $eventType = $displayer->getEventType();

            // Vérifier si on doit afficher ce type d'événement
            $shouldDisplay = $showAll || match ($eventType) {
                'database' => $showDb,
                'cache' => $showCache,
                'http' => $showHttp,
                'jobs' => $showJobs,
                default => $showAll,
            };

            if ($shouldDisplay) {
                $displayer->display($command, $trace, $options);
            }
        }

        // Afficher un résumé statistique si demandé
        if ($showAll && ! ($options['compact'] ?? false)) {
            $this->displayEventsSummary($command, $trace);
        }
    }

    /**
     * Formate une trace selon le format spécifié
     */
    public function formatOutput(TraceData $trace, string $format): string
    {
        if (! isset($this->outputFormatters[$format])) {
            throw new InvalidArgumentException("Unsupported output format: {$format}");
        }

        return $this->outputFormatters[$format]->format($trace);
    }

    /**
     * Génère un test à partir d'une trace
     */
    public function generateTest(TraceData $trace, string $testType, string $outputPath): string
    {
        if (! isset($this->testGenerators[$testType])) {
            throw new InvalidArgumentException("Unsupported test type: {$testType}");
        }

        return $this->testGenerators[$testType]->generate($trace, $outputPath);
    }

    /**
     * Récupère les événements pour un type donné
     */
    private function getEventsForType(TraceData $trace, string $eventType): array
    {
        return match ($eventType) {
            'database' => $trace->database,
            'cache' => $trace->cache,
            'http' => $trace->http,
            'jobs' => $trace->jobs,
            'mail' => $trace->mail,
            'notifications' => $trace->notifications,
            'events' => $trace->events,
            'filesystem' => $trace->filesystem,
            default => [],
        };
    }

    /**
     * Affiche un résumé statistique des événements
     */
    private function displayEventsSummary(Command $command, TraceData $trace): void
    {
        $totalEvents = 0;
        $summaries = [];

        foreach ($this->eventDisplayers as $displayer) {
            $eventType = $displayer->getEventType();
            $events = $this->getEventsForType($trace, $eventType);
            $count = count($events);

            if ($count > 0) {
                $totalEvents += $count;
                $summaries[$eventType] = [
                    'count' => $count,
                    'summary' => $displayer->getSummary($events),
                ];
            }
        }

        if ($totalEvents === 0) {
            $command->warn('🤷 No events captured in this trace.');

            return;
        }

        $command->warn('📈 EVENTS SUMMARY');

        foreach ($summaries as $eventType => $data) {
            $icon = match ($eventType) {
                'database' => '📊',
                'cache' => '🗄️',
                'http' => '🌐',
                'jobs' => '⚙️',
                default => '📝',
            };

            $command->line("  {$icon} " . ucfirst($eventType) . " events: {$data['count']}");

            // Afficher des statistiques détaillées si disponibles
            if ($eventType === 'database' && isset($data['summary']['queries'])) {
                $queries = is_numeric($data['summary']['queries']) ? (string) $data['summary']['queries'] : 'N/A';
                $avgTime = is_numeric($data['summary']['avg_time']) ? (string) $data['summary']['avg_time'] : 'N/A';
                $command->line("    └── Queries: {$queries}, Avg time: {$avgTime}ms");
            } elseif ($eventType === 'cache' && isset($data['summary']['hit_rate'])) {
                $hitRate = is_numeric($data['summary']['hit_rate']) ? (string) $data['summary']['hit_rate'] : 'N/A';
                $command->line("    └── Hit rate: {$hitRate}%");
            } elseif ($eventType === 'http' && isset($data['summary']['success_rate'])) {
                $successRate = is_numeric($data['summary']['success_rate']) ? (string) $data['summary']['success_rate'] : 'N/A';
                $command->line("    └── Success rate: {$successRate}%");
            } elseif ($eventType === 'jobs' && isset($data['summary']['success_rate'])) {
                $successRate = is_numeric($data['summary']['success_rate']) ? (string) $data['summary']['success_rate'] : 'N/A';
                $command->line("    └── Success rate: {$successRate}%");
            }
        }

        $command->line("  📝 Total events: {$totalEvents}");
        $command->newLine();
    }

    /**
     * Ajoute un displayer personnalisé
     */
    public function addEventDisplayer(EventDisplayerInterface $displayer): void
    {
        $this->eventDisplayers[] = $displayer;
    }

    /**
     * Ajoute un formatter personnalisé
     */
    public function addOutputFormatter(OutputFormatterInterface $formatter): void
    {
        $this->outputFormatters[] = $formatter;
    }

    /**
     * Ajoute un générateur de test personnalisé
     */
    public function addTestGenerator(TestGeneratorInterface $generator): void
    {
        $this->testGenerators[] = $generator;
    }
}
