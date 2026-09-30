<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Support;

/**
 * Resolves a fully-qualified class name to a file path using the target
 * project's own composer.json PSR-4 map (autoload + autoload-dev). This is
 * what lets class-hierarchy walking (e.g. Yii2AuthResolver) work on any
 * PSR-4 project without per-project configuration - no autoloading or
 * code execution involved, just reading the same map Composer itself uses.
 */
final class PsrAutoloadResolver
{
    /** @var array<string, string> namespace prefix (with trailing backslash) => base directory */
    private readonly array $map;

    private function __construct(array $map)
    {
        $this->map = $map;
    }

    public static function forProjectRoot(string $projectRoot): self
    {
        $composerJsonPath = rtrim($projectRoot, '/') . '/composer.json';
        if (!is_file($composerJsonPath)) {
            return new self([]);
        }

        $data = json_decode((string) file_get_contents($composerJsonPath), true) ?? [];
        $map = [];

        foreach (['autoload', 'autoload-dev'] as $section) {
            foreach ($data[$section]['psr-4'] ?? [] as $prefix => $dir) {
                $dirs = is_array($dir) ? $dir : [$dir];
                foreach ($dirs as $d) {
                    $map[$prefix] = rtrim($projectRoot, '/') . '/' . rtrim((string) $d, '/');
                }
            }
        }

        return new self($map);
    }

    /**
     * Resolves an FQCN (no leading backslash) to a file path, by longest
     * matching PSR-4 namespace prefix. Returns null if no prefix matches
     * or the resolved file doesn't exist (e.g. a framework/vendor class
     * outside this project's own autoload map).
     */
    public function resolve(string $fqcn): ?string
    {
        $fqcn = ltrim($fqcn, '\\');
        $bestPrefix = null;

        foreach (array_keys($this->map) as $prefix) {
            if (!str_starts_with($fqcn, $prefix)) {
                continue;
            }
            if ($bestPrefix === null || strlen($prefix) > strlen($bestPrefix)) {
                $bestPrefix = $prefix;
            }
        }

        if ($bestPrefix === null) {
            return null;
        }

        $relative = substr($fqcn, strlen($bestPrefix));
        $path = $this->map[$bestPrefix] . '/' . str_replace('\\', '/', $relative) . '.php';

        return is_file($path) ? $path : null;
    }
}
