<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Extractor;

/**
 * Where a RouteCandidate's method/path came from - used by Scanner to
 * decide which candidates are authoritative and which are a fallback
 * guess that a same-file apidoc.js block should suppress.
 */
enum RouteSource
{
    /** #[Route]/#[Get]/... attribute - always authoritative. */
    case Attribute;

    /** A free-standing apidoc.js `@api {verb} path` comment block - always authoritative. */
    case ApidocBlock;

    /** Resolved from a Yii2 urlManager rule (Yii2Config) - always authoritative. */
    case UrlManager;

    /** Guessed from a Yii2/Laravel naming convention - dropped for a file that also has an ApidocBlock route. */
    case Convention;
}
