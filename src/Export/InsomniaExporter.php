<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Export;

use Jeytekdev\ApiSync\Ir\Collection;
use Jeytekdev\ApiSync\Ir\Endpoint;
use Jeytekdev\ApiSync\Ir\Param;
use Jeytekdev\ApiSync\Support\BodyExample;

/**
 * Insomnia v4 export format. IDs are derived deterministically from the
 * endpoint's stable ID (Jeytekdev\ApiSync\Sync\CollectionMerger relies on this to
 * update requests in place instead of duplicating them on every run).
 */
final class InsomniaExporter implements CollectionExporterInterface
{
    public function name(): string
    {
        return 'insomnia';
    }

    public function export(Collection $collection, ?string $environmentName = null): array
    {
        $workspaceId = 'wrk_' . $this->hash($collection->name);
        $resources = [
            [
                '_id' => $workspaceId,
                '_type' => 'workspace',
                'name' => $collection->name,
            ],
            [
                '_id' => 'env_' . $this->hash($collection->name . ':base'),
                '_type' => 'environment',
                'parentId' => $workspaceId,
                'name' => $environmentName ?? 'Base environment',
                'data' => ['baseUrl' => $collection->baseUrl ?? 'http://localhost', 'authToken' => ''],
            ],
        ];

        $byGroup = [];
        foreach ($collection->endpoints as $endpoint) {
            $byGroup[$endpoint->group][] = $endpoint;
        }

        foreach ($byGroup as $group => $endpoints) {
            $folderId = 'fld_' . $this->hash($collection->name . ':' . $group);
            $resources[] = [
                '_id' => $folderId,
                '_type' => 'request_group',
                'parentId' => $workspaceId,
                'name' => $group,
            ];

            foreach ($endpoints as $endpoint) {
                $resources[] = $this->request($endpoint, $folderId);
            }
        }

        $document = [
            '_type' => 'export',
            '__export_format' => 4,
            '__export_source' => 'api-sync',
            'resources' => $resources,
        ];

        return ['collection.insomnia.json' => json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"];
    }

    private function request(Endpoint $endpoint, string $folderId): array
    {
        $queryParams = array_values(array_filter($endpoint->params, static fn (Param $p) => $p->in === Param::IN_QUERY));
        $headerParams = array_values(array_filter($endpoint->params, static fn (Param $p) => $p->in === Param::IN_HEADER));
        $bodyParams = array_values(array_filter($endpoint->params, static fn (Param $p) => $p->in === Param::IN_BODY));

        return [
            '_id' => 'req_' . $this->hash($endpoint->stableId()),
            '_type' => 'request',
            'parentId' => $folderId,
            '_apiSyncId' => $endpoint->stableId(),
            '_apiSyncManaged' => true,
            'name' => $endpoint->name ?? ($endpoint->method . ' ' . $endpoint->path),
            'description' => $endpoint->description ?? '',
            'method' => $endpoint->method,
            'url' => '{{ _.baseUrl }}' . $endpoint->path,
            'headers' => array_map(static fn (Param $p) => [
                'name' => $p->name,
                'value' => (string) ($p->example ?? ''),
                'description' => $p->description,
            ], $headerParams),
            'parameters' => array_map(static fn (Param $p) => [
                'name' => $p->name,
                'value' => (string) ($p->example ?? ''),
                'description' => $p->description,
                'disabled' => !$p->required,
            ], $queryParams),
            'body' => $bodyParams !== [] ? ['mimeType' => 'application/json', 'text' => BodyExample::json($bodyParams)] : [],
            'authentication' => $endpoint->auth !== null ? ['type' => $endpoint->auth] : [],
        ];
    }

    private function hash(string $value): string
    {
        return substr(md5($value), 0, 12);
    }
}
