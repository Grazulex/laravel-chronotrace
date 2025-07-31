<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Services;

/**
 * Service de nettoyage des données sensibles (PII)
 */
class PIIScrubber
{
    /** @var array<string> */
    private readonly array $scrubFields;

    /** @var array<string> */
    private readonly array $scrubPatterns;

    public function __construct()
    {
        $scrubFields = config('chronotrace.scrub', [
            'password', 'token', 'authorization', 'credit_card', 'ssn', 'email',
        ]);
        $this->scrubFields = is_array($scrubFields) ? $scrubFields : [];

        $scrubPatterns = config('chronotrace.scrub_patterns', [
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // emails
            '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/', // cartes de crédit
        ]);
        $this->scrubPatterns = is_array($scrubPatterns) ? $scrubPatterns : [];
    }

    /**
     * Nettoie un tableau de données sensibles
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    public function scrubArray(array $data): array
    {
        if (! config('chronotrace.scrub.enabled', true)) {
            return $data;
        }

        return $this->scrubArrayRecursive($data);
    }

    /**
     * Nettoie une chaîne de caractères
     */
    public function scrubString(string $content): string
    {
        if (! config('chronotrace.scrub.enabled', true)) {
            return $content;
        }

        foreach ($this->scrubPatterns as $pattern) {
            $result = preg_replace($pattern, '[SCRUBBED]', $content);
            if ($result !== null) {
                $content = $result;
            }
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    private function scrubArrayRecursive(array $data): array
    {
        $scrubbed = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $scrubbed[$key] = $this->scrubArrayRecursive($value);
            } elseif (is_string($key) && $this->shouldScrubField($key)) {
                $scrubbed[$key] = '[SCRUBBED]';
            } else {
                $scrubbed[$key] = is_string($value) ? $this->scrubString($value) : $value;
            }
        }

        return $scrubbed;
    }

    private function shouldScrubField(string $fieldName): bool
    {
        $lowerFieldName = strtolower($fieldName);

        foreach ($this->scrubFields as $scrubField) {
            if (is_string($scrubField) && str_contains($lowerFieldName, strtolower($scrubField))) {
                return true;
            }
        }

        return false;
    }
}
