<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Doc;

use Jeytekdev\ApiSync\Extractor\RouteCandidate;
use Jeytekdev\ApiSync\Support\Yii2AuthResolver;

/**
 * Infers `auth` from the controller's `behaviors()` chain (see
 * Yii2AuthResolver). Registered as the lowest-priority reader so an
 * explicit `@apiPermission`/`#[Auth(...)]` still overrides the inferred
 * scheme when present.
 */
final class Yii2AuthDocReader implements DocReaderInterface
{
    public function __construct(private readonly Yii2AuthResolver $resolver)
    {
    }

    public function read(RouteCandidate $candidate): DocFragment
    {
        if ($candidate->controllerFqcn === null) {
            return new DocFragment();
        }

        return new DocFragment(auth: $this->resolver->authFor($candidate->controllerFqcn));
    }
}
