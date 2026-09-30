<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Doc;

use Jeytekdev\ApiSync\Extractor\RouteCandidate;

interface DocReaderInterface
{
    public function read(RouteCandidate $candidate): DocFragment;
}
