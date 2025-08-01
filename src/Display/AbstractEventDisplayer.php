<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Display;

use Grazulex\LaravelChronotrace\Contracts\EventDisplayerInterface;
use Grazulex\LaravelChronotrace\Models\TraceData;

/**
 * Classe de base abstraite pour l'affichage d'événements
 */
abstract class AbstractEventDisplayer implements EventDisplayerInterface
{
    /**
     * Formate une valeur de manière sécurisée pour l'affichage
     */
    protected function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_object($value)) {
            return $value::class;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return 'unknown';
    }

    /**
     * Extrait une valeur string d'un array de manière sécurisée
     *
     * @param  array<mixed>  $array
     */
    protected function getStringValue(array $array, string $key, string $default = ''): string
    {
        if (! isset($array[$key])) {
            return $default;
        }

        $value = $array[$key];
        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return $this->formatValue($value);
        }

        return $default;
    }

    /**
     * Vérifie si une clé existe dans l'array
     *
     * @param  array<mixed>  $array
     */
    protected function hasKey(array $array, string $key): bool
    {
        return isset($array[$key]);
    }

    /**
     * Formate un timestamp de manière sécurisée
     *
     * @param  array<mixed>  $event
     */
    protected function getTimestampFormatted(array $event): string
    {
        if (! isset($event['timestamp'])) {
            return 'N/A';
        }

        $timestamp = $event['timestamp'];
        if (is_numeric($timestamp)) {
            return date('H:i:s.v', (int) $timestamp);
        }

        return 'N/A';
    }

    /**
     * Vérifie si des options sont définies pour l'affichage détaillé
     *
     * @param  array<string, mixed>  $options
     */
    protected function shouldShowDetailed(array $options): bool
    {
        return (bool) ($options['detailed'] ?? false);
    }

    /**
     * Vérifie si des bindings doivent être affichés
     *
     * @param  array<string, mixed>  $options
     */
    protected function shouldShowBindings(array $options): bool
    {
        return (bool) ($options['bindings'] ?? false);
    }

    /**
     * Récupère les événements d'un type spécifique
     *
     * @return array<mixed>
     */
    protected function getEventsByType(TraceData $trace, string $type): array
    {
        return match ($type) {
            'database' => $trace->database,
            'cache' => $trace->cache,
            'http' => $trace->http,
            'jobs' => $trace->jobs,
            default => [],
        };
    }
}
