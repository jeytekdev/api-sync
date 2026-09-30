<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Extractor;

use PhpParser\Node;

/**
 * A route found by a route extractor, together with everything a Doc
 * reader needs to enrich it (docblock text + attribute nodes), before it
 * is turned into a Jeytekdev\ApiSync\Ir\Endpoint. $methodNode is a ClassMethod
 * for attribute-based/Yii2 extractors, the enclosing statement for
 * call-based extractors (e.g. Laravel's `Route::get(...)`), or null for
 * ApidocRouteExtractor, which has no single AST node to point at - its
 * $rawDocText carries the comment block directly instead.
 */
final class RouteCandidate
{
    public function __construct(
        public readonly string $httpMethod,
        public readonly string $path,
        public readonly string $group,
        public readonly ?string $name,
        public readonly ?Node $methodNode,
        public readonly string $sourceFile,
        public readonly RouteSource $source = RouteSource::Convention,
        public readonly ?string $rawDocText = null,
        /** The controller class's FQCN, when known - used by Yii2AuthDocReader to walk the class hierarchy for auth. */
        public readonly ?string $controllerFqcn = null,
    ) {
    }

    public function docComment(): ?string
    {
        if ($this->rawDocText !== null) {
            return $this->rawDocText;
        }

        $doc = $this->methodNode?->getDocComment();

        return $doc?->getText();
    }
}
