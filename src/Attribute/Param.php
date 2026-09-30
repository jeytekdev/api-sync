<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Param
{
    public function __construct(
        public readonly string $name,
        public readonly string $in = 'query',
        public readonly string $type = 'string',
        public readonly bool $required = false,
        public readonly ?string $description = null,
        public readonly mixed $example = null,
    ) {
    }
}
