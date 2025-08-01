<?php

namespace Grazulex\LaravelChronotrace\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'chronotrace:install {--force : Overwrite existing configuration}';

    protected $description = 'Install ChronoTrace middleware and configuration';

    public function handle(): int
    {
        $this->info('Installing ChronoTrace...');

        // 1. Publier la configuration
        $this->call('vendor:publish', [
            '--tag' => 'chronotrace-config',
            '--force' => $this->option('force'),
        ]);

        // 2. Vérifier la version de Laravel
        $laravelVersion = $this->getLaravelVersion();

        if (version_compare($laravelVersion, '11.0', '>=')) {
            $this->installForLaravel11();
        } else {
            $this->installForLaravelLegacy();
        }

        $this->info('✅ ChronoTrace installation completed!');
        $this->newLine();
        $this->info('🚀 You can now start using ChronoTrace:');
        $this->line('   php artisan chronotrace:list');
        $this->line('   php artisan chronotrace:record https://example.com');
        $this->line('   php artisan chronotrace:test-internal  # Test internal operations');
        $this->line('   php artisan chronotrace:diagnose       # Check configuration');
        $this->newLine();
        $this->info('📖 For more help, check the documentation at:');
        $this->line('   https://github.com/Grazulex/laravel-chronotrace');

        return Command::SUCCESS;
    }

    private function installForLaravel11(): void
    {
        $this->info('📱 Detected Laravel 11+ - Configuring bootstrap/app.php...');

        $bootstrapPath = base_path('bootstrap/app.php');

        if (! File::exists($bootstrapPath)) {
            $this->error('❌ bootstrap/app.php not found!');

            return;
        }

        $content = File::get($bootstrapPath);

        // Vérifier si le middleware est déjà configuré
        if (str_contains($content, 'ChronoTraceMiddleware')) {
            $this->warn('⚠️  ChronoTrace middleware already configured in bootstrap/app.php');

            return;
        }

        // Rechercher le pattern withMiddleware
        if (preg_match('/->withMiddleware\(function \(([^)]+)\) use \([^)]*\)[^{]*{/', $content)) {
            // Pattern avec use()
            $middlewareCode = <<<'PHP'
        $middleware->web(append: [
            \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
        ]);
        $middleware->api(append: [
            \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
        ]);
PHP;
        } elseif (preg_match('/->withMiddleware\(function \(([^)]+)\)[^{]*{/', $content)) {
            // Pattern simple
            $middlewareCode = <<<'PHP'
        $middleware->web(append: [
            \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
        ]);
        $middleware->api(append: [
            \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
        ]);
PHP;
        } else {
            // Pas de withMiddleware trouvé
            $this->showManualInstructions();

            return;
        }

        // Injection du middleware
        $pattern = '/(\->withMiddleware\(function \([^)]+\)(?:\s+use\s*\([^)]*\))?\s*{\s*)/';
        $replacement = '$1' . "\n" . $middlewareCode . "\n";

        $newContent = preg_replace($pattern, $replacement, $content);

        if ($newContent && $newContent !== $content) {
            File::put($bootstrapPath, $newContent);
            $this->info('✅ ChronoTrace middleware automatically added to bootstrap/app.php');
        } else {
            $this->showManualInstructions();
        }
    }

    private function installForLaravelLegacy(): void
    {
        $this->info('📱 Detected Laravel <11 - Middleware will be auto-registered');
        $this->info('✅ No additional configuration needed!');
    }

    private function showManualInstructions(): void
    {
        $this->warn('⚠️  Could not automatically configure middleware.');
        $this->info('📝 Please add this to your bootstrap/app.php manually:');
        $this->newLine();

        $this->line('<comment>->withMiddleware(function (Middleware $middleware) {</comment>');
        $this->line('<comment>    $middleware->web(append: [</comment>');
        $this->line('<comment>        \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,</comment>');
        $this->line('<comment>    ]);</comment>');
        $this->line('<comment>    $middleware->api(append: [</comment>');
        $this->line('<comment>        \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,</comment>');
        $this->line('<comment>    ]);</comment>');
        $this->line('<comment>})</comment>');
        $this->newLine();
        $this->info('💡 After adding the middleware, test with:');
        $this->line('   php artisan chronotrace:test-internal');
        $this->line('   php artisan chronotrace:diagnose');
    }

    private function getLaravelVersion(): string
    {
        return $this->laravel->version();
    }
}
