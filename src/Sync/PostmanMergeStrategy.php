<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Sync;

final class PostmanMergeStrategy implements JsonMergeStrategyInterface
{
    private const DEPRECATED_FOLDER = '_deprecated';

    public function __construct(private readonly bool $prune)
    {
    }

    public function merge(array $existing, array $generated): array
    {
        $existingFolders = $this->normalizeFolders($existing['item'] ?? []);

        $existingById = [];
        $unmanagedByFolder = [];
        foreach ($existingFolders as $folderName => $items) {
            foreach ($items as $item) {
                if (isset($item['_apiSyncId'])) {
                    $existingById[$item['_apiSyncId']] = $item;
                } else {
                    $unmanagedByFolder[$folderName][] = $item;
                }
            }
        }

        $matchedIds = [];
        $generatedGroups = [];
        $outputFolders = [];

        foreach ($generated['item'] ?? [] as $folder) {
            $groupName = $folder['name'];
            $generatedGroups[$groupName] = true;

            $mergedItems = [];
            foreach ($folder['item'] as $genItem) {
                $id = $genItem['_apiSyncId'];
                if (isset($existingById[$id])) {
                    $mergedItems[] = $this->mergeItem($existingById[$id], $genItem);
                    $matchedIds[$id] = true;
                } else {
                    $mergedItems[] = $genItem;
                }
            }

            foreach ($unmanagedByFolder[$groupName] ?? [] as $manual) {
                $mergedItems[] = $manual;
            }

            $outputFolders[] = ['name' => $groupName, 'item' => $mergedItems];
        }

        // Unmanaged (hand-written) items in folders that no longer correspond
        // to any generated group would otherwise be lost - keep their folder.
        foreach ($unmanagedByFolder as $folderName => $items) {
            if (isset($generatedGroups[$folderName])) {
                continue;
            }
            $outputFolders[] = ['name' => $folderName, 'item' => $items];
        }

        // Any managed item whose ID never matched a generated one was
        // removed from the code - move it to _deprecated instead of losing it.
        $deprecated = [];
        if (!$this->prune) {
            foreach ($existingById as $id => $item) {
                if (!isset($matchedIds[$id])) {
                    $item['_apiSyncDeprecated'] = true;
                    $deprecated[] = $item;
                }
            }
        }

        if ($deprecated !== []) {
            $outputFolders[] = ['name' => self::DEPRECATED_FOLDER, 'item' => $deprecated];
        }

        $generated['item'] = $outputFolders;

        return $generated;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function normalizeFolders(array $rawItems): array
    {
        $folders = [];
        foreach ($rawItems as $entry) {
            if (isset($entry['item']) && is_array($entry['item'])) {
                $folders[$entry['name'] ?? '_root'] = $entry['item'];
            } else {
                $folders['_root'][] = $entry;
            }
        }

        return $folders;
    }

    private function mergeItem(array $existingItem, array $generatedItem): array
    {
        $merged = $existingItem;
        $merged['name'] = $generatedItem['name'];

        // The code is authoritative for the request shape, except the body:
        // only overwrite it when the code actually declares one
        // (#[Param(in: 'body')]) - otherwise keep whatever was saved before.
        $request = $generatedItem['request'];
        if (($request['body'] ?? null) === null) {
            $request['body'] = $existingItem['request']['body'] ?? null;
        }
        $merged['request'] = $request;

        $merged['_apiSyncId'] = $generatedItem['_apiSyncId'];
        $merged['_apiSyncManaged'] = true;
        unset($merged['_apiSyncDeprecated']);

        return $merged;
    }
}
