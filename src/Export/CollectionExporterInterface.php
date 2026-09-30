<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Export;

use Jeytekdev\ApiSync\Ir\Collection;

interface CollectionExporterInterface
{
    public function name(): string;

    /**
     * @return array<string, string> relative file path => file content
     */
    public function export(Collection $collection, ?string $environmentName = null): array;
}
