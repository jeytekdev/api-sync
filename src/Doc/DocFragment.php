<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Doc;

use Jeytekdev\ApiSync\Ir\Param;
use Jeytekdev\ApiSync\Ir\Response;

/**
 * Partial endpoint description read from a single source (attributes or
 * apidoc.js docblock). Fields are null/empty when not provided by that
 * source, so fragments from multiple readers can be merged field-by-field.
 */
final class DocFragment
{
    /**
     * @param Param[] $params
     * @param Response[] $responses
     */
    public function __construct(
        public readonly ?string $group = null,
        public readonly ?string $name = null,
        public readonly ?string $version = null,
        public readonly array $params = [],
        public readonly ?string $auth = null,
        public readonly array $responses = [],
        public readonly ?string $description = null,
    ) {
    }

    /**
     * Merges $this on top of $base: any non-empty field on $this wins,
     * otherwise $base's value is kept. Used to apply attribute-sourced
     * fragments (higher priority) over apidoc.js-sourced ones (fallback).
     */
    public function mergeOver(self $base): self
    {
        return new self(
            group: $this->group ?? $base->group,
            name: $this->name ?? $base->name,
            version: $this->version ?? $base->version,
            params: $this->params !== [] ? $this->params : $base->params,
            auth: $this->auth ?? $base->auth,
            responses: $this->responses !== [] ? $this->responses : $base->responses,
            description: $this->description ?? $base->description,
        );
    }
}
