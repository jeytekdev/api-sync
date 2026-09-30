<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Sync;

final class InsomniaMergeStrategy implements JsonMergeStrategyInterface
{
    public function __construct(private readonly bool $prune)
    {
    }

    public function merge(array $existing, array $generated): array
    {
        $existingById = [];
        foreach ($existing['resources'] ?? [] as $resource) {
            $existingById[$resource['_id']] = $resource;
        }

        $matchedIds = [];
        $merged = [];

        foreach ($generated['resources'] ?? [] as $genResource) {
            $id = $genResource['_id'];
            $matchedIds[$id] = true;

            if (!isset($existingById[$id])) {
                $merged[] = $genResource;
                continue;
            }

            $merged[] = match ($genResource['_type']) {
                'request' => $this->mergeRequest($existingById[$id], $genResource),
                'environment' => $genResource + ['data' => $existingById[$id]['data'] ?? $genResource['data']],
                default => $genResource,
            };
        }

        foreach ($existing['resources'] ?? [] as $resource) {
            if (isset($matchedIds[$resource['_id']])) {
                continue;
            }

            $isManagedRequest = ($resource['_type'] ?? null) === 'request' && isset($resource['_apiSyncId']);
            if ($isManagedRequest) {
                if ($this->prune) {
                    continue;
                }
                $resource['_apiSyncDeprecated'] = true;
            }

            $merged[] = $resource;
        }

        $generated['resources'] = $merged;

        return $generated;
    }

    private function mergeRequest(array $existing, array $generated): array
    {
        $merged = $existing;
        foreach (['name', 'description', 'method', 'url', 'headers', 'parameters', '_apiSyncId', '_apiSyncManaged'] as $key) {
            $merged[$key] = $generated[$key];
        }

        // Only overwrite the body when the code actually declares one
        // (#[Param(in: 'body')]) - otherwise keep whatever the developer
        // typed into Insomnia by hand.
        if (($generated['body'] ?? []) !== []) {
            $merged['body'] = $generated['body'];
        }

        unset($merged['_apiSyncDeprecated']);

        return $merged;
    }
}
