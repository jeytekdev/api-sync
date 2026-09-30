<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Doc;

use Jeytekdev\ApiSync\Extractor\RouteCandidate;
use Jeytekdev\ApiSync\Ir\Param;
use Jeytekdev\ApiSync\Ir\Response;
use Jeytekdev\ApiSync\Support\AttributeReader;

/**
 * Highest-priority doc source: reads Jeytekdev\ApiSync\Attribute\* (or any
 * attribute with the same short name, e.g. from OpenAPI/Symfony-style
 * packages) directly from the AST - no autoloading of the attribute
 * classes required.
 */
final class AttributeDocReader implements DocReaderInterface
{
    public function read(RouteCandidate $candidate): DocFragment
    {
        $node = $candidate->methodNode;
        if ($node === null) {
            return new DocFragment();
        }

        $params = [];
        foreach (AttributeReader::attributesOn($node, 'Param') as $attr) {
            $a = AttributeReader::args($attr);
            $name = $a['name'] ?? $a[0] ?? null;
            if (!is_string($name)) {
                continue;
            }
            $params[] = new Param(
                name: $name,
                in: (string) ($a['in'] ?? 'query'),
                type: (string) ($a['type'] ?? 'string'),
                required: (bool) ($a['required'] ?? false),
                description: is_string($a['description'] ?? null) ? $a['description'] : null,
                example: $a['example'] ?? null,
            );
        }

        $responses = [];
        foreach (AttributeReader::attributesOn($node, 'Response') as $attr) {
            $a = AttributeReader::args($attr);
            $status = $a['status'] ?? $a[0] ?? null;
            if (!is_int($status)) {
                continue;
            }
            $responses[] = new Response(
                status: $status,
                description: is_string($a['description'] ?? null) ? $a['description'] : null,
                example: $a['example'] ?? null,
            );
        }

        $group = $this->firstArg($node, 'Group');
        $version = $this->firstArg($node, 'Version');
        $auth = $this->firstArg($node, 'Auth');
        $summary = $this->firstArg($node, 'Summary');

        return new DocFragment(
            group: $group,
            version: $version,
            params: $params,
            auth: $auth,
            responses: $responses,
            description: $summary,
        );
    }

    private function firstArg(\PhpParser\Node $node, string $attributeShortName): ?string
    {
        foreach (AttributeReader::attributesOn($node, $attributeShortName) as $attr) {
            $args = AttributeReader::args($attr);
            $value = $args[0] ?? reset($args);

            return is_string($value) ? $value : null;
        }

        return null;
    }
}
