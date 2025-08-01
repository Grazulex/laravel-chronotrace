<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Contracts;

use Grazulex\LaravelChronotrace\Models\TraceData;
use Illuminate\Console\Command;

/**
 * Interface pour les classes d'affichage d'événements
 */
interface EventDisplayerInterface
{
    /**
     * Affiche les événements dans la console
     *
     * @param  array<string, mixed>  $options  Options d'affichage
     */
    public function display(Command $command, TraceData $trace, array $options = []): void;

    /**
     * Retourne le nom du type d'événement
     */
    public function getEventType(): string;

    /**
     * Vérifie si ce displayer peut gérer ce type d'événement
     */
    public function canHandle(string $eventType): bool;

    /**
     * Génère un résumé statistique pour ce type d'événement
     *
     * @param  array<mixed>  $events
     */
    public function getSummary(array $events): array;
}
