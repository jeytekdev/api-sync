<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Sync;

interface JsonMergeStrategyInterface
{
    /**
     * @param array<string, mixed> $existing decoded collection currently on disk
     * @param array<string, mixed> $generated freshly generated collection
     * @return array<string, mixed>
     */
    public function merge(array $existing, array $generated): array;
}
