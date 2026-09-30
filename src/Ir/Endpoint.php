<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Ir;

final class Endpoint
{
    /**
     * @param Param[] $params
     * @param Response[] $responses
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $group = 'default',
        public readonly ?string $name = null,
        public readonly ?string $version = null,
        public readonly array $params = [],
        public readonly ?string $auth = null,
        public readonly array $responses = [],
        public readonly ?string $description = null,
        public readonly ?string $sourceFile = null,
    ) {
    }

    /**
     * Stable identifier used by the merge engine to match endpoints across
     * regenerations, regardless of where in the collection they end up.
     */
    public function stableId(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        return strtoupper($this->method) . ' ' . $this->normalizedPath();
    }

    private function normalizedPath(): string
    {
        return preg_replace('/\{[^}]+\}/', '{}', $this->path) ?? $this->path;
    }
}
