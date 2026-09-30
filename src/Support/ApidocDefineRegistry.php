<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Support;

/**
 * Holds every `@apiDefine <Name>` block found anywhere across the scanned
 * source, so `@apiUse <Name>` in an endpoint's own docblock can pull in
 * its params/headers - matching real apidoc.js semantics, where a define
 * block is typically shared across many actions/files (e.g. a common
 * "Authorization header" block), not physically attached to any one of
 * them.
 */
final class ApidocDefineRegistry
{
    /** @var array<string, string> defineName => raw doc-comment text */
    private array $defines = [];

    public function add(string $name, string $blockText): void
    {
        $this->defines[$name] = $blockText;
    }

    public function get(string $name): ?string
    {
        return $this->defines[$name] ?? null;
    }
}
