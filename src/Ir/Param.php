<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Ir;

final class Param
{
    public const IN_PATH = 'path';
    public const IN_QUERY = 'query';
    public const IN_BODY = 'body';
    public const IN_HEADER = 'header';

    public function __construct(
        public readonly string $name,
        public readonly string $in,
        public readonly string $type = 'string',
        public readonly bool $required = false,
        public readonly ?string $description = null,
        public readonly mixed $example = null,
    ) {
    }
}
