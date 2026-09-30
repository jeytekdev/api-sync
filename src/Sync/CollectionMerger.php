<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Sync;

use Jeytekdev\ApiSync\Export\BrunoExporter;
use Jeytekdev\ApiSync\Export\PostmanExporter;

/**
 * Regenerating a collection must not throw away manual work: saved
 * examples/tests in Postman, edited body/auth in Insomnia, or a request
 * a developer wrote by hand. This merges freshly generated files against
 * whatever already exists on disk, per format, instead of overwriting
 * blindly.
 *
 * Removed endpoints are never deleted silently - they're moved into a
 * "_deprecated" bucket unless $prune is true.
 */
final class CollectionMerger
{
    public function __construct(private readonly bool $prune = false)
    {
    }

    /**
     * Environment files (baseUrl/authToken) are never "_apiSync"-managed
     * per item - regenerating one would blindly wipe out a value the user
     * actually filled in, so they're only written the first time (when
     * missing), never overwritten afterwards. Bruno's already gets this
     * for free (its content has no managed marker, so mergeBruno's normal
     * marker check already leaves it alone); Insomnia's is inline in the
     * collection file and already preserved by InsomniaMergeStrategy.
     */
    private const ENVIRONMENT_FILE_BY_FORMAT = [
        'postman' => PostmanExporter::ENVIRONMENT_FILE,
    ];

    /**
     * @param array<string, string> $generatedFiles relative path => content
     * @return array<string, string> relative path => content actually written
     */
    public function merge(string $format, array $generatedFiles, string $outputDir): array
    {
        $envKey = self::ENVIRONMENT_FILE_BY_FORMAT[$format] ?? null;
        $envResult = $envKey !== null ? $this->keepIfMissing($generatedFiles, $envKey, $outputDir) : [];
        if ($envKey !== null) {
            unset($generatedFiles[$envKey]);
        }

        $mainResult = match ($format) {
            'postman' => $this->mergeJsonCollection($generatedFiles, $outputDir, 'collection.postman.json', new PostmanMergeStrategy($this->prune)),
            'insomnia' => $this->mergeJsonCollection($generatedFiles, $outputDir, 'collection.insomnia.json', new InsomniaMergeStrategy($this->prune)),
            'bruno' => $this->mergeBruno($generatedFiles, $outputDir),
            default => $generatedFiles,
        };

        return $mainResult + $envResult;
    }

    /**
     * @param array<string, string> $generatedFiles
     * @return array<string, string>
     */
    private function keepIfMissing(array $generatedFiles, string $key, string $outputDir): array
    {
        if (!isset($generatedFiles[$key])) {
            return [];
        }

        if (is_file(rtrim($outputDir, '/') . '/' . $key)) {
            return [];
        }

        return [$key => $generatedFiles[$key]];
    }

    /**
     * @param array<string, string> $generatedFiles
     * @return array<string, string>
     */
    private function mergeJsonCollection(array $generatedFiles, string $outputDir, string $fileName, JsonMergeStrategyInterface $strategy): array
    {
        $generated = json_decode($generatedFiles[$fileName] ?? '{}', true, flags: JSON_THROW_ON_ERROR);

        $existingPath = rtrim($outputDir, '/') . '/' . $fileName;
        $existing = is_file($existingPath)
            ? json_decode((string) file_get_contents($existingPath), true, flags: JSON_THROW_ON_ERROR)
            : null;

        $merged = $existing === null ? $generated : $strategy->merge($existing, $generated);

        return [$fileName => json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"];
    }

    /**
     * @param array<string, string> $generatedFiles
     * @return array<string, string>
     */
    private function mergeBruno(array $generatedFiles, string $outputDir): array
    {
        $written = [];
        $outputDir = rtrim($outputDir, '/');

        foreach ($generatedFiles as $relativePath => $content) {
            $absolutePath = $outputDir . '/' . $relativePath;

            if (!is_file($absolutePath)) {
                $written[$relativePath] = $content;
                continue;
            }

            $existing = (string) file_get_contents($absolutePath);
            if (str_starts_with($existing, BrunoExporter::MANAGED_MARKER)) {
                // Still managed: safe to regenerate in place.
                $written[$relativePath] = $content;
            }
            // Marker removed by a developer -> file opted out of auto-sync, leave untouched.
        }

        if ($this->prune) {
            return $written;
        }

        foreach ($this->staleManagedFiles($generatedFiles, $outputDir) as $stalePath => $staleContent) {
            $written['_deprecated/' . $stalePath] = $staleContent;
        }

        return $written;
    }

    /**
     * @param array<string, string> $generatedFiles
     * @return array<string, string>
     */
    private function staleManagedFiles(array $generatedFiles, string $outputDir): array
    {
        if (!is_dir($outputDir)) {
            return [];
        }

        $stale = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($outputDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'bru') {
                continue;
            }

            $relativePath = ltrim(str_replace($outputDir, '', $file->getPathname()), '/');
            if (str_starts_with($relativePath, '_deprecated/') || isset($generatedFiles[$relativePath])) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            if (str_starts_with($content, BrunoExporter::MANAGED_MARKER)) {
                $stale[$relativePath] = $content;
            }
        }

        return $stale;
    }
}
