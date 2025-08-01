<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Display\Formatters;

use Grazulex\LaravelChronotrace\Contracts\OutputFormatterInterface;
use Grazulex\LaravelChronotrace\Models\TraceData;

/**
 * Formatter pour la sortie brute (sérialisée)
 */
class RawOutputFormatter implements OutputFormatterInterface
{
    public function format(TraceData $trace): string
    {
        return serialize($trace);
    }

    public function getFormatType(): string
    {
        return 'raw';
    }

    public function canHandle(string $formatType): bool
    {
        return $formatType === 'raw';
    }
}
