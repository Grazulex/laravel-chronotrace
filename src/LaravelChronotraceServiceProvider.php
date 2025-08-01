<?php

namespace Grazulex\LaravelChronotrace;

use Exception;
use Grazulex\LaravelChronotrace\Commands\DiagnoseCommand;
use Grazulex\LaravelChronotrace\Commands\InstallCommand;
use Grazulex\LaravelChronotrace\Commands\ListCommand;
use Grazulex\LaravelChronotrace\Commands\MiddlewareTestCommand;
use Grazulex\LaravelChronotrace\Commands\PurgeCommand;
use Grazulex\LaravelChronotrace\Commands\RecordCommand;
use Grazulex\LaravelChronotrace\Commands\ReplayCommand;
use Grazulex\LaravelChronotrace\Display\DisplayManager;
use Grazulex\LaravelChronotrace\Display\Events\CacheEventDisplayer;
use Grazulex\LaravelChronotrace\Display\Events\DatabaseEventDisplayer;
use Grazulex\LaravelChronotrace\Display\Events\HttpEventDisplayer;
use Grazulex\LaravelChronotrace\Display\Events\JobEventDisplayer;
use Grazulex\LaravelChronotrace\Display\Formatters\JsonOutputFormatter;
use Grazulex\LaravelChronotrace\Display\Formatters\RawOutputFormatter;
use Grazulex\LaravelChronotrace\Display\TestGenerators\PestTestGenerator;
use Grazulex\LaravelChronotrace\Listeners\CacheEventListener;
use Grazulex\LaravelChronotrace\Listeners\DatabaseEventListener;
use Grazulex\LaravelChronotrace\Listeners\HttpEventListener;
use Grazulex\LaravelChronotrace\Listeners\QueueEventListener;
use Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware;
use Grazulex\LaravelChronotrace\Services\PIIScrubber;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider;
use Override;

class LaravelChronotraceServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/chronotrace.php', 'chronotrace');

        // Enregistrer les services
        $this->app->singleton(PIIScrubber::class);
        $this->app->singleton(TraceRecorder::class);
        $this->app->singleton(TraceStorage::class, function ($app): TraceStorage {
            $storage = config('chronotrace.storage', 'local');
            $compression = config('chronotrace.compression.enabled', true);

            // Mapper le type de stockage vers le disk Laravel approprié
            $disk = match ($storage) {
                's3' => $this->configureS3Disk(),
                'local' => 'local',
                default => 'local'
            };

            return new TraceStorage(
                $disk,
                is_bool($compression) ? $compression : true
            );
        });

        // Enregistrer les event listeners
        $this->app->singleton(DatabaseEventListener::class);
        $this->app->singleton(CacheEventListener::class);
        $this->app->singleton(HttpEventListener::class);
        $this->app->singleton(QueueEventListener::class);

        // Enregistrer les event displayers
        $this->app->singleton(DatabaseEventDisplayer::class);
        $this->app->singleton(CacheEventDisplayer::class);
        $this->app->singleton(HttpEventDisplayer::class);
        $this->app->singleton(JobEventDisplayer::class);

        // Enregistrer les output formatters
        $this->app->singleton(JsonOutputFormatter::class);
        $this->app->singleton(RawOutputFormatter::class);

        // Enregistrer les test generators
        $this->app->singleton(PestTestGenerator::class);

        // Enregistrer le DisplayManager
        $this->app->singleton(DisplayManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/chronotrace.php' => config_path('chronotrace.php'),
            ], 'chronotrace-config');

            $this->commands([
                InstallCommand::class,
                RecordCommand::class,
                ReplayCommand::class,
                ListCommand::class,
                PurgeCommand::class,
                DiagnoseCommand::class,
                MiddlewareTestCommand::class,
            ]);

            // Détection automatique d'installation
            $this->detectAndRunInstallation();
        }

        // Enregistrer le middleware si ChronoTrace est activé
        if (config('chronotrace.enabled', false)) {
            // Laravel 11+ : Recommander l'enregistrement manuel dans bootstrap/app.php
            // Mais essayer quand même l'ancienne méthode pour compatibilité
            try {
                $router = $this->app->make('router');
                $router->pushMiddlewareToGroup('web', ChronoTraceMiddleware::class);
                $router->pushMiddlewareToGroup('api', ChronoTraceMiddleware::class);
            } catch (Exception) {
                // Échec silencieux - l'utilisateur devra configurer manuellement
                // dans bootstrap/app.php pour Laravel 11+
            }

            // Enregistrer les event listeners
            $this->registerEventListeners();
        }
    }

    /**
     * Détecte et exécute l'installation automatiquement si nécessaire
     */
    private function detectAndRunInstallation(): void
    {
        // Vérifier si ChronoTrace n'est pas encore installé
        $configPath = config_path('chronotrace.php');
        $storageDir = storage_path('chronotrace');

        // Si le fichier de config n'existe pas, c'est une première installation
        if (! file_exists($configPath)) {
            $this->showInstallationMessage();

            return;
        }

        // Vérifier si le dossier de stockage existe
        if (! is_dir($storageDir)) {
            $this->showInstallationMessage();

            return;
        }

        // Vérifier la version du package pour détecter une mise à jour
        $this->checkForUpdates();
    }

    /**
     * Affiche un message d'installation
     */
    private function showInstallationMessage(): void
    {
        // Ne pas afficher de messages pendant les tests ou l'analyse statique
        if (app()->environment('testing') || defined('PHPSTAN_RUNNING') || isset($_SERVER['argv']) && in_array('analyse', $_SERVER['argv'], true)) {
            return;
        }

        if (defined('ARTISAN_BINARY')) {
            echo "\n";
            echo "🚀 ChronoTrace detected! Run the installation command:\n";
            echo "   php artisan chronotrace:install\n";
            echo "\n";
        }
    }

    /**
     * Vérifie les mises à jour du package
     */
    private function checkForUpdates(): void
    {
        $configPath = config_path('chronotrace.php');
        if (file_exists($configPath)) {
            $configContent = file_get_contents($configPath);

            // Chercher une version dans le fichier de config (si on en ajoute une)
            // Pour l'instant, on peut juste afficher un message de mise à jour disponible
            if (defined('ARTISAN_BINARY') && $configContent !== false && str_contains($configContent, '// Generated by ChronoTrace')) {
                // On pourrait vérifier la version ici et afficher un message de mise à jour
                // echo "\n🔄 ChronoTrace update detected! Run: php artisan chronotrace:install --force\n\n";
            }
        }
    }

    /**
     * Enregistre les event listeners pour capturer les événements Laravel
     */
    private function registerEventListeners(): void
    {
        $events = $this->app->make('events');

        // Database events
        if (config('chronotrace.capture.database', true)) {
            $events->listen(QueryExecuted::class, [DatabaseEventListener::class, 'handleQueryExecuted']);
            $events->listen(TransactionBeginning::class, [DatabaseEventListener::class, 'handleTransactionBeginning']);
            $events->listen(TransactionCommitted::class, [DatabaseEventListener::class, 'handleTransactionCommitted']);
            $events->listen(TransactionRolledBack::class, [DatabaseEventListener::class, 'handleTransactionRolledBack']);
        }

        // Cache events
        if (config('chronotrace.capture.cache', true)) {
            $events->listen(CacheHit::class, [CacheEventListener::class, 'handleCacheHit']);
            $events->listen(CacheMissed::class, [CacheEventListener::class, 'handleCacheMissed']);
            $events->listen(KeyWritten::class, [CacheEventListener::class, 'handleKeyWritten']);
            $events->listen(KeyForgotten::class, [CacheEventListener::class, 'handleKeyForgotten']);
        }

        // HTTP events (Laravel HTTP Client)
        if (config('chronotrace.capture.http', true)) {
            $events->listen(RequestSending::class, [HttpEventListener::class, 'handleRequestSending']);
            $events->listen(ResponseReceived::class, [HttpEventListener::class, 'handleResponseReceived']);
            $events->listen(ConnectionFailed::class, [HttpEventListener::class, 'handleConnectionFailed']);
        }

        // Queue/Job events
        if (config('chronotrace.capture.jobs', true)) {
            $events->listen(JobProcessing::class, [QueueEventListener::class, 'handleJobProcessing']);
            $events->listen(JobProcessed::class, [QueueEventListener::class, 'handleJobProcessed']);
            $events->listen(JobFailed::class, [QueueEventListener::class, 'handleJobFailed']);
        }
    }

    /**
     * Configure le disk S3/MinIO pour ChronoTrace
     */
    private function configureS3Disk(): string
    {
        $config = config('chronotrace.s3', []);

        // Configuration du disk S3 pour ChronoTrace
        config(['filesystems.disks.chronotrace_s3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => $config['region'] ?? env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => $config['bucket'] ?? env('CHRONOTRACE_S3_BUCKET', 'chronotrace'),
            'url' => $config['endpoint'] ?? env('CHRONOTRACE_S3_ENDPOINT'),
            'endpoint' => $config['endpoint'] ?? env('CHRONOTRACE_S3_ENDPOINT'),
            'use_path_style_endpoint' => ! empty($config['endpoint']) || ! empty(env('CHRONOTRACE_S3_ENDPOINT')), // Pour MinIO
            'root' => $config['path_prefix'] ?? 'traces',
            'visibility' => 'private',
            'throw' => true, // Lever des exceptions en cas d'erreur
        ]]);

        return 'chronotrace_s3';
    }
}
