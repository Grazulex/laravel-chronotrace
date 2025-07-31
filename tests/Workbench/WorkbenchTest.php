<?php

namespace Grazulex\LaravelChronotrace\Tests\Workbench;

use Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider;
use Grazulex\LaravelChronotrace\Services\PIIScrubber;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Override;

class WorkbenchTest extends TestCase
{
    use WithWorkbench;

    protected function getPackageProviders($app): array
    {
        return [
            LaravelChronotraceServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Configuration spécifique au workbench
        $app['config']->set('chronotrace.enabled', true);
        $app['config']->set('chronotrace.mode', 'always');
        $app['config']->set('chronotrace.async_storage', false);

        // Configuration de stockage pour les tests
        $app['config']->set('filesystems.disks.chronotrace_test', [
            'driver' => 'local',
            'root' => storage_path('app/chronotrace-test'),
        ]);

        $app['config']->set('chronotrace.storage.disk', 'chronotrace_test');
        $app['config']->set('chronotrace.storage.path', 'traces');
        $app['config']->set('chronotrace.storage.compression', true);
        $app['config']->set('chronotrace.storage.compression_level', 6);

        // Configuration du scrubber
        $app['config']->set('chronotrace.scrub', [
            'password', 'token', 'authorization', 'credit_card', 'ssn', 'email',
        ]);

        $app['config']->set('chronotrace.scrub_patterns', [
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // emails
            '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/', // cartes de crédit
        ]);
    }

    public function test_workbench_package_services_are_registered(): void
    {
        // Vérifier que tous les services principaux sont enregistrés
        $this->assertInstanceOf(PIIScrubber::class, $this->app->make(PIIScrubber::class));
        $this->assertInstanceOf(TraceRecorder::class, $this->app->make(TraceRecorder::class));
        $this->assertInstanceOf(TraceStorage::class, $this->app->make(TraceStorage::class));
    }

    public function test_workbench_configuration_is_published(): void
    {
        // Simuler la publication de la configuration
        $this->artisan('vendor:publish', [
            '--tag' => 'chronotrace-config',
            '--force' => true,
        ])->assertExitCode(0);

        // Vérifier que le fichier de config existe
        $configPath = config_path('chronotrace.php');
        $this->assertFileExists($configPath);

        // Vérifier le contenu
        $config = require $configPath;
        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('mode', $config);
        $this->assertArrayHasKey('storage', $config);
        $this->assertArrayHasKey('scrub', $config);
    }

    public function test_workbench_commands_are_available(): void
    {
        // Tester que toutes les commandes sont disponibles
        $kernel = $this->app->make(Kernel::class);
        $allCommands = $kernel->all();

        $expectedCommands = [
            'chronotrace:list',
            'chronotrace:purge',
            'chronotrace:record',
            'chronotrace:replay',
        ];

        foreach ($expectedCommands as $command) {
            $this->assertArrayHasKey($command, $allCommands, "Command {$command} should be available");
        }
    }

    public function test_workbench_can_run_full_workflow(): void
    {
        // Test workflow complet avec le workbench

        // 1. Vérifier la configuration
        $this->assertTrue(config('chronotrace.enabled'));

        // 2. Tester le service PIIScrubber
        $scrubber = $this->app->make(PIIScrubber::class);
        $scrubbed = $scrubber->scrubArray(['password' => 'secret', 'name' => 'John']);
        $this->assertSame('[SCRUBBED]', $scrubbed['password']);
        $this->assertSame('John', $scrubbed['name']);

        // 3. Tester les commandes
        $this->artisan('chronotrace:list')
            ->expectsOutput('No traces found.')
            ->assertExitCode(0);

        $this->artisan('chronotrace:purge', ['--confirm' => true])
            ->expectsOutput('Successfully purged 0 traces.')
            ->assertExitCode(0);

        // 4. Tester le stockage
        $storage = $this->app->make(TraceStorage::class);
        $traces = $storage->list();
        $this->assertIsArray($traces);
        $this->assertEmpty($traces);
    }

    public function test_workbench_filesystem_setup(): void
    {
        // Vérifier que le système de fichiers est correctement configuré
        $disk = Storage::disk('chronotrace_test');

        // Tester l'écriture
        $testFile = 'test-' . uniqid() . '.txt';
        $disk->put($testFile, 'test content');

        $this->assertTrue($disk->exists($testFile));
        $this->assertSame('test content', $disk->get($testFile));

        // Nettoyer
        $disk->delete($testFile);
    }

    public function test_workbench_environment_variables(): void
    {
        // Vérifier que l'environnement est correctement configuré pour les tests
        $this->assertSame('testing', $this->app->environment());
        $this->assertTrue($this->app->runningInConsole());
    }

    #[Override]
    protected function tearDown(): void
    {
        // Nettoyer les fichiers de test
        if (Storage::disk('chronotrace_test')->exists('traces')) {
            Storage::disk('chronotrace_test')->deleteDirectory('traces');
        }

        parent::tearDown();
    }
}
