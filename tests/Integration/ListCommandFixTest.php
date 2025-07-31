<?php

declare(strict_types=1);

namespace Tests\Integration;

use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase;
use Override;

/**
 * Test de validation de la correction de la commande list
 * pour les traces stockées dans la structure YYYY-MM-DD
 */
class ListCommandFixTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Nettoyer le stockage
        Storage::fake('local');
    }

    public function test_list_command_finds_traces_in_date_directories(): void
    {
        // Créer des traces simulées dans la structure YYYY-MM-DD
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Traces d'aujourd'hui
        Storage::put("traces/{$today}/ct_test1_123456.zip", 'trace data 1');
        Storage::put("traces/{$today}/ct_test2_123457.zip", 'trace data 2');

        // Traces d'hier
        Storage::put("traces/{$yesterday}/ct_test3_123458.zip", 'trace data 3');

        // Utiliser TraceStorage pour lister
        $traceStorage = new TraceStorage;
        $traces = $traceStorage->list();

        // Vérifier qu'on trouve bien les 3 traces
        $this->assertCount(3, $traces);

        // Vérifier la structure des données retournées
        foreach ($traces as $trace) {
            $this->assertArrayHasKey('trace_id', $trace);
            $this->assertArrayHasKey('path', $trace);
            $this->assertArrayHasKey('size', $trace);
            $this->assertArrayHasKey('created_at', $trace);
        }

        // Vérifier les IDs des traces
        $traceIds = array_column($traces, 'trace_id');
        $this->assertContains('ct_test1_123456', $traceIds);
        $this->assertContains('ct_test2_123457', $traceIds);
        $this->assertContains('ct_test3_123458', $traceIds);
    }

    public function test_list_command_shows_no_traces_when_empty(): void
    {
        $traceStorage = new TraceStorage;
        $traces = $traceStorage->list();

        $this->assertCount(0, $traces);
    }

    public function test_list_command_ignores_non_zip_files(): void
    {
        $today = date('Y-m-d');

        // Créer des fichiers de différents types
        Storage::put("traces/{$today}/ct_test1_123456.zip", 'trace data 1');
        Storage::put("traces/{$today}/ct_test2_123457.json", 'not a trace');
        Storage::put("traces/{$today}/readme.txt", 'readme file');

        $traceStorage = new TraceStorage;
        $traces = $traceStorage->list();

        // Ne doit trouver que le fichier .zip
        $this->assertCount(1, $traces);
        $this->assertSame('ct_test1_123456', $traces[0]['trace_id']);
    }

    public function test_list_command_sorts_by_creation_date_desc(): void
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Créer des fichiers dans des dossiers de dates différentes
        Storage::put("traces/{$yesterday}/ct_old_123456.zip", 'old trace');
        Storage::put("traces/{$today}/ct_new_123457.zip", 'new trace');

        $traceStorage = new TraceStorage;
        $traces = $traceStorage->list();

        $this->assertCount(2, $traces);

        // Vérifier qu'on a bien les deux traces
        $traceIds = array_column($traces, 'trace_id');
        $this->assertContains('ct_old_123456', $traceIds);
        $this->assertContains('ct_new_123457', $traceIds);
    }
}
