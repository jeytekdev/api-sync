<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Ir;

final class Collection
{
    /**
     * @param Endpoint[] $endpoints
     */
    public function __construct(
        public readonly string $name,
        public readonly array $endpoints = [],
        public readonly ?string $baseUrl = null,
    ) {
    }
}
