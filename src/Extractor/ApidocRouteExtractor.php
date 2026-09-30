<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Extractor;

use Jeytekdev\ApiSync\Support\ApidocSyntax;
use Jeytekdev\ApiSync\Support\AstFile;
use PhpParser\Node\Stmt\Class_;

/**
 * apidoc.js blocks are plain text comments, not tied to any specific
 * PHP node - real-world codebases commonly stack them as a free-standing
 * header before the class, one block per action, rather than attaching
 * each one to its method. This extractor mirrors apidoc.js itself: it
 * scans every doc-comment in the file for an `@api {verb} path` tag and
 * treats each match as its own, fully authoritative route - regardless
 * of where in the file it physically sits.
 */
final class ApidocRouteExtractor implements RouteExtractorInterface
{
    public function extract(AstFile $file): array
    {
        $candidates = [];
        $controllerFqcn = $this->soleClassFqcn($file);

        foreach ($file->docCommentBlocks() as $block) {
            $tag = ApidocSyntax::firstApiTagIn($block);
            if ($tag === null) {
                continue;
            }

            $candidates[] = new RouteCandidate(
                httpMethod: $tag['method'],
                path: $tag['path'],
                group: 'default',
                name: null,
                methodNode: null,
                sourceFile: $file->path,
                source: RouteSource::ApidocBlock,
                rawDocText: $block,
                controllerFqcn: $controllerFqcn,
            );
        }

        return $candidates;
    }

    /**
     * apidoc.js blocks have no AST attachment to correlate them to a
     * specific class, but Yii2/PSR-4 controllers are virtually always one
     * class per file - so when a file declares exactly one class, that's
     * unambiguously the controller these blocks document (needed for
     * Yii2AuthDocReader to walk its behaviors() chain). Ambiguous files
     * (zero or several classes) are left without a controller FQCN rather
     * than guessing wrong.
     */
    private function soleClassFqcn(AstFile $file): ?string
    {
        $classes = $file->find(Class_::class);

        return count($classes) === 1 ? $classes[0]->namespacedName?->toString() : null;
    }
}
