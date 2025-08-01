<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Contracts;

use Grazulex\LaravelChronotrace\Models\TraceData;

/**
 * Interface pour les classes de formatage de sortie
 */
interface OutputFormatterInterface
{
    /**
     * Formate la trace selon le format spécifique
     */
    public function format(TraceData $trace): string;

    /**
     * Retourne le type de format géré
     */
    public function getFormatType(): string;

    /**
     * Vérifie si ce formatter peut gérer ce type de format
     */
    public function canHandle(string $formatType): bool;
}
