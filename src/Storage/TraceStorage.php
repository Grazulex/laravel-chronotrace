<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Storage;

use Grazulex\LaravelChronotrace\Models\TraceData;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * Gestionnaire de stockage pour les traces ChronoTrace
 * Crée des bundles auto-contenus avec compression
 */
class TraceStorage
{
    public function __construct(
        private readonly string $disk = 'local',
        private readonly bool $compression = true,
    ) {}

    /**
     * Stocke une trace complète en tant que bundle compressé
     */
    public function store(TraceData $trace): string
    {
        $bundlePath = $this->getBundlePath($trace->traceId);

        // Créer le dossier temporaire pour le bundle
        $tempDir = sys_get_temp_dir() . '/chronotrace_' . $trace->traceId;
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            // 1. Créer le manifest principal
            $manifest = $this->createManifest($trace);
            file_put_contents($tempDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            // 2. Séparer les gros payloads en blobs
            $this->extractLargePayloads($trace, $tempDir);

            // 3. Créer l'archive ZIP
            $this->createBundle($tempDir, $bundlePath);

            return $bundlePath;
        } finally {
            // Nettoyer le dossier temporaire
            $this->cleanupDirectory($tempDir);
        }
    }

    /**
     * Récupère une trace depuis son bundle
     */
    public function retrieve(string $traceId): ?TraceData
    {
        $bundlePath = $this->getBundlePath($traceId);

        if (! Storage::disk($this->disk)->exists($bundlePath)) {
            return null;
        }

        $tempDir = sys_get_temp_dir() . '/chronotrace_read_' . $traceId;

        try {
            // Extraire le bundle
            $this->extractBundle($bundlePath, $tempDir);

            // Lire le manifest
            $manifestPath = $tempDir . '/manifest.json';
            if (! file_exists($manifestPath)) {
                return null;
            }

            $manifestContent = file_get_contents($manifestPath);
            if ($manifestContent === false) {
                return null;
            }

            $manifest = json_decode($manifestContent, true);
            if (! is_array($manifest)) {
                return null;
            }

            // Reconstituer les gros payloads
            $this->restoreLargePayloads($manifest, $tempDir);

            return TraceData::fromArray($manifest);
        } finally {
            $this->cleanupDirectory($tempDir);
        }
    }

    /**
     * Liste toutes les traces stockées
     */
    public function list(): array
    {
        $traces = [];

        try {
            // Lister tous les dossiers de dates dans traces/
            $tracesDir = 'traces';
            if (! Storage::disk($this->disk)->exists($tracesDir)) {
                return [];
            }

            $dateDirs = Storage::disk($this->disk)->directories($tracesDir);

            foreach ($dateDirs as $dateDir) {
                if (! is_string($dateDir)) {
                    continue;
                }

                try {
                    $files = Storage::disk($this->disk)->files($dateDir);

                    foreach ($files as $file) {
                        if (str_ends_with($file, '.zip')) {
                            $traceId = basename($file, '.zip');
                            $traces[] = [
                                'trace_id' => $traceId,
                                'path' => $file,
                                'size' => Storage::disk($this->disk)->size($file),
                                'created_at' => Storage::disk($this->disk)->lastModified($file),
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    // Ignorer les erreurs de dossiers individuels pour continuer le listing
                    continue;
                }
            }

            // Trier par date de création décroissante (plus récent en premier)
            usort($traces, fn ($a, $b): int => $b['created_at'] <=> $a['created_at']);

            return $traces;
        } catch (\Exception $e) {
            // En cas d'erreur générale, retourner un tableau vide
            return [];
        }
    }

    /**
     * Supprime une trace
     */
    public function delete(string $traceId): bool
    {
        $bundlePath = $this->getBundlePath($traceId);

        if (Storage::disk($this->disk)->exists($bundlePath)) {
            return Storage::disk($this->disk)->delete($bundlePath);
        }

        return false;
    }

    /**
     * Purge les traces anciennes selon la politique de rétention
     */
    public function purgeOldTraces(int $retentionDays = 15): int
    {
        $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
        $deleted = 0;

        foreach ($this->list() as $trace) {
            if ($trace['created_at'] >= $cutoffTime) {
                continue;
            }
            if (! $this->delete($trace['trace_id'])) {
                continue;
            }
            $deleted++;
        }

        return $deleted;
    }

    private function getBundlePath(string $traceId): string
    {
        $date = date('Y-m-d');

        return "traces/{$date}/{$traceId}.zip";
    }

    private function createManifest(TraceData $trace): array
    {
        $manifest = $trace->toArray();

        // Ajouter des métadonnées du bundle
        $manifest['_bundle'] = [
            'version' => '1.0',
            'created_at' => date('c'),
            'compression' => $this->compression,
            'large_payloads' => [],
        ];

        return $manifest;
    }

    private function extractLargePayloads(TraceData $trace, string $tempDir): void
    {
        $maxSize = config('chronotrace.compression.max_payload_size', 1024 * 1024);

        // Vérifier la taille du contenu de la réponse
        if (strlen($trace->response->content) > $maxSize) {
            $blobPath = $tempDir . '/response_content.blob';
            file_put_contents($blobPath, $trace->response->content);
            // Marquer pour remplacement dans le manifest
        }

        // Vérifier les autres gros payloads (DB, HTTP, etc.)
        // TODO: Implémenter extraction des autres gros payloads
    }

    private function createBundle(string $tempDir, string $bundlePath): void
    {
        $zip = new ZipArchive;
        $tempZipPath = sys_get_temp_dir() . '/chronotrace_bundle_' . uniqid() . '.zip';

        try {
            if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $this->addDirectoryToZip($zip, $tempDir, '');
                $zip->close();

                // Upload le fichier ZIP selon le type de disk
                if ($this->isS3Disk()) {
                    // Pour S3, utiliser put avec le contenu du fichier
                    $zipContent = file_get_contents($tempZipPath);
                    if ($zipContent !== false) {
                        Storage::disk($this->disk)->put($bundlePath, $zipContent);
                    }
                } else {
                    // Pour les disks locaux, utiliser l'ancienne méthode
                    $fullPath = Storage::disk($this->disk)->path($bundlePath);
                    $parentDir = dirname($fullPath);
                    if (! is_dir($parentDir)) {
                        mkdir($parentDir, 0755, true);
                    }
                    copy($tempZipPath, $fullPath);
                }
            }
        } finally {
            // Nettoyer le fichier ZIP temporaire
            if (file_exists($tempZipPath)) {
                unlink($tempZipPath);
            }
        }
    }

    private function extractBundle(string $bundlePath, string $tempDir): void
    {
        $zip = new ZipArchive;
        
        if ($this->isS3Disk()) {
            // Pour S3, télécharger d'abord le fichier en local
            $tempZipPath = sys_get_temp_dir() . '/chronotrace_extract_' . uniqid() . '.zip';
            
            try {
                $zipContent = Storage::disk($this->disk)->get($bundlePath);
                if ($zipContent !== null) {
                    file_put_contents($tempZipPath, $zipContent);
                    
                    if ($zip->open($tempZipPath) === true) {
                        $zip->extractTo($tempDir);
                        $zip->close();
                    }
                }
            } finally {
                if (file_exists($tempZipPath)) {
                    unlink($tempZipPath);
                }
            }
        } else {
            // Pour les disks locaux, utiliser l'ancienne méthode
            $fullPath = Storage::disk($this->disk)->path($bundlePath);
            
            if ($zip->open($fullPath) === true) {
                $zip->extractTo($tempDir);
                $zip->close();
            }
        }
    }

    /**
     * Vérifie si le disk courant est un disk S3
     */
    private function isS3Disk(): bool
    {
        $diskConfig = config("filesystems.disks.{$this->disk}");
        return isset($diskConfig['driver']) && $diskConfig['driver'] === 's3';
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function restoreLargePayloads(array &$manifest, string $tempDir): void
    {
        // Restaurer le contenu de la réponse si stocké en blob
        $blobPath = $tempDir . '/response_content.blob';
        if (file_exists($blobPath)) {
            $manifest['response']['content'] = file_get_contents($blobPath);
        }

        // TODO: Restaurer les autres gros payloads
    }

    private function addDirectoryToZip(ZipArchive $zip, string $path, string $relativePath): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && ! $file->isDir()) {
                $filePath = $file->getRealPath();
                if ($filePath !== false) {
                    $relativeFilePath = $relativePath . substr($filePath, strlen($path) + 1);
                    $zip->addFile($filePath, $relativeFilePath);
                }
            }
        }
    }

    private function cleanupDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $this->deleteDirectory($dir);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
