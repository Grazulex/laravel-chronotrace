<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Display\TestGenerators;

use Grazulex\LaravelChronotrace\Contracts\TestGeneratorInterface;
use Grazulex\LaravelChronotrace\Models\TraceData;

/**
 * Générateur de tests Pest
 */
class PestTestGenerator implements TestGeneratorInterface
{
    public function generate(TraceData $trace, string $outputPath): string
    {
        $testFile = $outputPath . '/' . 'ChronoTrace_' . substr($trace->traceId, 0, 8) . '_Test.php';

        // Créer le dossier si nécessaire
        if (! is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $testContent = $this->buildTestContent($trace);
        file_put_contents($testFile, $testContent);

        return $testFile;
    }

    public function getTestType(): string
    {
        return 'pest';
    }

    public function buildTestContent(TraceData $trace): string
    {
        $requestMethod = strtolower($trace->request->method);
        $requestUrl = $trace->request->url;
        $responseStatus = $trace->response->status;
        $testName = "trace replay for {$trace->request->method} {$requestUrl}";

        $testContent = "<?php\n\n";
        $testContent .= "/**\n";
        $testContent .= " * Generated Pest test from ChronoTrace\n";
        $testContent .= " * Trace ID: {$trace->traceId}\n";
        $testContent .= ' * Generated at: ' . date('Y-m-d H:i:s') . "\n";
        $testContent .= " */\n\n";

        // Imports nécessaires
        $testContent .= "use Illuminate\\Foundation\\Testing\\RefreshDatabase;\n";
        $testContent .= "use Illuminate\\Support\\Facades\\Cache;\n";
        $testContent .= "use Illuminate\\Support\\Facades\\DB;\n\n";

        // Test principal
        $testContent .= "it('{$testName}', function () {\n";

        // Setup des données si POST/PUT/PATCH
        if (in_array(strtoupper($requestMethod), ['POST', 'PUT', 'PATCH']) && $trace->request->input !== []) {
            $testContent .= '    $requestData = ' . $this->formatPHPArray($trace->request->input) . ";\n\n";
        }

        // Extraire le path de l'URL complète
        $urlPath = parse_url($requestUrl, PHP_URL_PATH) ?: '/';
        $queryString = parse_url($requestUrl, PHP_URL_QUERY);
        if ($queryString) {
            $urlPath .= '?' . $queryString;
        }

        // Requête HTTP
        $testContent .= "    \$response = \$this->{$requestMethod}('{$urlPath}'";

        if (in_array(strtoupper($requestMethod), ['POST', 'PUT', 'PATCH']) && $trace->request->input !== []) {
            $testContent .= ', $requestData';
        }

        // Headers (filtrer et nettoyer)
        $cleanHeaders = $this->filterHeaders($trace->request->headers);
        if ($cleanHeaders !== []) {
            $testContent .= ', ' . $this->formatPHPArray($cleanHeaders);
        }

        $testContent .= ");\n\n";

        // Assertions de base
        $testContent .= "    \$response->assertStatus({$responseStatus});\n";

        // Assertions de structure de réponse
        if ($trace->response->content !== '' && $trace->response->content !== '0') {
            $responseData = json_decode($trace->response->content, true);

            if (is_array($responseData)) {
                $structure = $this->extractJsonStructure($responseData);
                $testContent .= '    $response->assertJsonStructure(' . $this->formatPHPArray($structure) . ");\n";
            }
        }

        // Assertions pour les headers de réponse importants
        foreach ($trace->response->headers as $header => $value) {
            if (in_array(strtolower((string) $header), ['content-type', 'location', 'cache-control'])) {
                $headerValue = $this->getFirstHeaderValue($value);
                if ($headerValue !== 'unknown' && $headerValue !== '') {
                    $testContent .= "    \$response->assertHeader('{$header}', '{$headerValue}');\n";
                }
            }
        }

        // Assertions de base de données si on a des queries
        if ($trace->database !== []) {
            $testContent .= "\n    // Database assertions from captured queries\n";
            $testContent .= "    expect(DB::getQueryLog())->not->toBeEmpty(); // Database queries should be executed\n";
        }

        // Assertions de cache si on a des opérations
        if ($trace->cache !== []) {
            $testContent .= "\n    // Cache assertions from captured operations\n";
            foreach ($trace->cache as $cacheOp) {
                if (is_array($cacheOp) && isset($cacheOp['type']) && $cacheOp['type'] === 'hit' && isset($cacheOp['key'])) {
                    $cacheKey = is_scalar($cacheOp['key']) ? (string) $cacheOp['key'] : 'unknown';
                    if ($cacheKey !== 'unknown') {
                        $testContent .= "    // Cache key '{$cacheKey}' should exist if reproduced\n";
                        $testContent .= "    // expect(Cache::has('{$cacheKey}'))->toBeTrue();\n";
                    }
                }
            }
        }

        $testContent .= "})->uses(Tests\\TestCase::class, RefreshDatabase::class);\n\n";

        // Test de performance
        if ($trace->response->duration > 0) {
            $maxDuration = $trace->response->duration * 2;
            $testContent .= "it('performs within acceptable time limits', function () {\n";
            $testContent .= "    \$start = microtime(true);\n";
            $testContent .= "    \$this->{$requestMethod}('{$urlPath}');\n";
            $testContent .= "    \$duration = microtime(true) - \$start;\n";
            $testContent .= "    expect(\$duration)->toBeLessThan({$maxDuration}); // Request should not take too long\n";
            $testContent .= "})->uses(Tests\\TestCase::class);\n";
        }

        return $testContent;
    }

    /**
     * Formate un tableau PHP avec une indentation propre
     *
     * @param  array<mixed>  $array
     */
    private function formatPHPArray(array $array): string
    {
        $export = var_export($array, true);
        $export = preg_replace('/^(\s+)/m', '    $1', $export);

        return $export ?: '';
    }

    /**
     * Filtre les headers pour garder seulement les utiles pour les tests
     *
     * @param  array<string, mixed>  $headers
     *
     * @return array<string, mixed>
     */
    private function filterHeaders(array $headers): array
    {
        $allowedHeaders = ['accept', 'content-type', 'authorization', 'x-requested-with'];
        $filtered = [];

        foreach ($headers as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (in_array($lowerKey, $allowedHeaders)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Extrait la structure JSON de manière récursive
     *
     * @param  array<mixed>  $data
     *
     * @return array<mixed>
     */
    private function extractJsonStructure(array $data): array
    {
        $structure = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $structure[$key] = $this->extractJsonStructure($value);
            } else {
                $structure[] = $key;
            }
        }

        return $structure;
    }

    /**
     * Récupère la première valeur d'un header
     */
    private function getFirstHeaderValue(mixed $value): string
    {
        if (is_array($value) && count($value) > 0) {
            $firstValue = $value[0];

            return is_scalar($firstValue) ? (string) $firstValue : 'unknown';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return 'unknown';
    }
}
