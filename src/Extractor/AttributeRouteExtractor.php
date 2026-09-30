<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Extractor;

use Jeytekdev\ApiSync\Support\AstFile;
use Jeytekdev\ApiSync\Support\AttributeReader;
use Jeytekdev\ApiSync\Support\Strings;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Framework-agnostic extractor: reads PHP 8 attributes straight from the
 * AST, so it works for Symfony/API Platform-style `#[Route]`, per-verb
 * attributes like `#[Get]`/`#[Post]`, or any custom attribute ending in
 * "Route" - without booting any framework.
 */
final class AttributeRouteExtractor implements RouteExtractorInterface
{
    private const VERB_SHORT_NAMES = ['Get', 'Post', 'Put', 'Patch', 'Delete', 'Options', 'Head'];

    public function extract(AstFile $file): array
    {
        $candidates = [];

        foreach ($file->find(ClassLike::class) as $class) {
            $group = $this->groupFor($class);

            foreach ($class->getMethods() as $method) {
                $candidate = $this->fromRouteAttribute($method, $group, $file->path)
                    ?? $this->fromVerbAttribute($method, $group, $file->path);

                if ($candidate !== null) {
                    $candidates[] = $candidate;
                }
            }
        }

        return $candidates;
    }

    private function groupFor(ClassLike $class): string
    {
        $name = $class->name?->toString() ?? 'default';

        return Strings::kebabCase(Strings::stripSuffix($name, 'Controller'));
    }

    private function fromRouteAttribute(\PhpParser\Node\Stmt\ClassMethod $method, string $group, string $file): ?RouteCandidate
    {
        foreach (AttributeReader::attributesOn($method, 'Route') as $attr) {
            $args = AttributeReader::args($attr);
            $path = $args['path'] ?? $args[0] ?? null;
            if (!is_string($path)) {
                continue;
            }

            $methods = $args['methods'] ?? ['GET'];
            $httpMethod = strtoupper(is_array($methods) ? (string) ($methods[0] ?? 'GET') : (string) $methods);
            $name = is_string($args['name'] ?? null) ? $args['name'] : null;

            return new RouteCandidate($httpMethod, $path, $group, $name, $method, $file, source: RouteSource::Attribute);
        }

        return null;
    }

    private function fromVerbAttribute(\PhpParser\Node\Stmt\ClassMethod $method, string $group, string $file): ?RouteCandidate
    {
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $shortName = AttributeReader::shortName($attr);
                if (!in_array($shortName, self::VERB_SHORT_NAMES, true)) {
                    continue;
                }

                $args = AttributeReader::args($attr);
                $path = $args['path'] ?? $args[0] ?? null;
                if (!is_string($path)) {
                    continue;
                }

                $name = is_string($args['name'] ?? null) ? $args['name'] : null;

                return new RouteCandidate(strtoupper($shortName), $path, $group, $name, $method, $file, source: RouteSource::Attribute);
            }
        }

        return null;
    }
}
