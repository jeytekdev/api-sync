<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Summary
{
    public function __construct(public readonly string $text)
    {
    }
}
