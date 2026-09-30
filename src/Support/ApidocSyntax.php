<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Support;

/**
 * Shared parsing for the apidoc.js `@api {verb} path [title]` tag, used
 * both to enrich a doc fragment with a title and, independently, to
 * discover routes straight from free-standing apidoc.js comment blocks
 * (see Extractor\ApidocRouteExtractor).
 */
final class ApidocSyntax
{
    /**
     * @return array{method: ?string, path: ?string, title: ?string}
     */
    public static function parseApiTag(string $apiLine): array
    {
        if (!preg_match('/^\{(\w+)\}\s*(\S+)\s*(.*)$/', trim($apiLine), $m)) {
            return ['method' => null, 'path' => null, 'title' => null];
        }

        return [
            'method' => strtoupper($m[1]),
            'path' => self::normalizePath($m[2]),
            'title' => trim($m[3]) ?: null,
        ];
    }

    /**
     * apidoc.js path params use ":name"; the rest of this package (IR,
     * exporters) uses OpenAPI/Postman-style "{name}".
     */
    public static function normalizePath(string $path): string
    {
        return preg_replace('/:(\w+)/', '{$1}', $path) ?? $path;
    }

    /**
     * Scans a raw docblock's text for its `@api` line and parses it.
     * Returns null if the block has no `@api` tag, or the tag doesn't
     * carry a resolvable {verb} + path.
     *
     * @return array{method: string, path: string, title: ?string}|null
     */
    public static function firstApiTagIn(string $docText): ?array
    {
        foreach (explode("\n", $docText) as $line) {
            $line = trim(ltrim(trim($line), "/* \t"));
            if (!preg_match('/^@api\s+(.*)$/', $line, $m)) {
                continue;
            }

            $tag = self::parseApiTag($m[1]);
            if ($tag['method'] !== null && $tag['path'] !== null) {
                return ['method' => $tag['method'], 'path' => $tag['path'], 'title' => $tag['title']];
            }
        }

        return null;
    }

    /**
     * Scans a raw docblock's text for an `@apiDefine <Name>` tag,
     * returning the defined name, or null if the block doesn't declare one.
     */
    public static function firstApiDefineNameIn(string $docText): ?string
    {
        foreach (explode("\n", $docText) as $line) {
            $line = trim(ltrim(trim($line), "/* \t"));
            if (preg_match('/^@apiDefine\s+(\S+)/', $line, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
