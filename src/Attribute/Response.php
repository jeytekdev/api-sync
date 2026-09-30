<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $description = null,
        public readonly mixed $example = null,
    ) {
    }
}
