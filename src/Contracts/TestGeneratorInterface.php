<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Contracts;

use Grazulex\LaravelChronotrace\Models\TraceData;

/**
 * Interface pour les générateurs de tests
 */
interface TestGeneratorInterface
{
    /**
     * Génère un test à partir d'une trace
     */
    public function generate(TraceData $trace, string $outputPath): string;

    /**
     * Retourne le type de test généré
     */
    public function getTestType(): string;

    /**
     * Construit le contenu du test
     */
    public function buildTestContent(TraceData $trace): string;
}
