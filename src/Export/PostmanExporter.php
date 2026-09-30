<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Export;

use Jeytekdev\ApiSync\Ir\Collection;
use Jeytekdev\ApiSync\Ir\Endpoint;
use Jeytekdev\ApiSync\Ir\Param;
use Jeytekdev\ApiSync\Support\BodyExample;

/**
 * Postman Collection Format v2.1. Every generated item carries
 * "_apiSyncId"/"_apiSyncManaged" markers (ignored by Postman itself) that
 * Jeytekdev\ApiSync\Sync\CollectionMerger uses to update a collection in place
 * without discarding manually added tests/examples.
 */
final class PostmanExporter implements CollectionExporterInterface
{
    public function name(): string
    {
        return 'postman';
    }

    public const ENVIRONMENT_FILE = 'environment.postman_environment.json';

    public function export(Collection $collection, ?string $environmentName = null): array
    {
        $byGroup = [];
        foreach ($collection->endpoints as $endpoint) {
            $byGroup[$endpoint->group][] = $endpoint;
        }

        $folders = [];
        foreach ($byGroup as $group => $endpoints) {
            $folders[] = [
                'name' => $group,
                'item' => array_map(fn (Endpoint $e) => $this->item($e), $endpoints),
            ];
        }

        $document = [
            'info' => [
                'name' => $collection->name,
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                ['key' => 'baseUrl', 'value' => $collection->baseUrl ?? 'http://localhost'],
            ],
            'item' => $folders,
        ];

        return [
            'collection.postman.json' => json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            self::ENVIRONMENT_FILE => $this->environment($environmentName ?? $collection->name, $collection->baseUrl),
        ];
    }

    private function environment(string $name, ?string $baseUrl): string
    {
        $document = [
            'name' => $name,
            '_postman_variable_scope' => 'environment',
            'values' => [
                ['key' => 'baseUrl', 'value' => $baseUrl ?? 'http://localhost', 'type' => 'default', 'enabled' => true],
                ['key' => 'authToken', 'value' => '', 'type' => 'secret', 'enabled' => true],
            ],
        ];

        return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function item(Endpoint $endpoint): array
    {
        $pathParams = array_values(array_filter($endpoint->params, static fn (Param $p) => $p->in === Param::IN_PATH));
        $queryParams = array_values(array_filter($endpoint->params, static fn (Param $p) => $p->in === Param::IN_QUERY));
        $headerParams = array_values(array_filter($endpoint->params, static fn (Param $p) => $p->in === Param::IN_HEADER));
        $bodyParams = array_values(array_filter($endpoint->params, static fn (Param $p) => $p->in === Param::IN_BODY));

        $rawPath = preg_replace('/\{(\w+)\}/', ':$1', $endpoint->path) ?? $endpoint->path;

        return [
            '_apiSyncId' => $endpoint->stableId(),
            '_apiSyncManaged' => true,
            'name' => $endpoint->name ?? ($endpoint->method . ' ' . $endpoint->path),
            'request' => [
                'method' => $endpoint->method,
                'header' => array_map(static fn (Param $p) => [
                    'key' => $p->name,
                    'value' => (string) ($p->example ?? ''),
                    'description' => $p->description,
                ], $headerParams),
                'url' => [
                    'raw' => '{{baseUrl}}' . $rawPath,
                    'host' => ['{{baseUrl}}'],
                    'path' => array_values(array_filter(explode('/', $rawPath))),
                    'query' => array_map(static fn (Param $p) => [
                        'key' => $p->name,
                        'value' => (string) ($p->example ?? ''),
                        'description' => $p->description,
                        'disabled' => !$p->required,
                    ], $queryParams),
                    'variable' => array_map(static fn (Param $p) => [
                        'key' => $p->name,
                        'value' => (string) ($p->example ?? ''),
                        'description' => $p->description,
                    ], $pathParams),
                ],
                'body' => $bodyParams !== [] ? [
                    'mode' => 'raw',
                    'raw' => BodyExample::json($bodyParams),
                    'options' => ['raw' => ['language' => 'json']],
                ] : null,
                'description' => $endpoint->description,
                'auth' => $endpoint->auth !== null ? ['type' => $endpoint->auth] : null,
            ],
            'response' => [],
        ];
    }
}
