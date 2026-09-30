<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Extractor;

use Jeytekdev\ApiSync\Support\AstFile;

interface RouteExtractorInterface
{
    /**
     * @return RouteCandidate[]
     */
    public function extract(AstFile $file): array;
}
