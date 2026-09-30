<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class Group
{
    public function __construct(public readonly string $name)
    {
    }
}
