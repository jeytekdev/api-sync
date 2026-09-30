<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Export;

use Jeytekdev\ApiSync\Ir\Collection;
use Jeytekdev\ApiSync\Ir\Endpoint;
use Jeytekdev\ApiSync\Ir\Param;
use Jeytekdev\ApiSync\Support\BodyExample;
use Jeytekdev\ApiSync\Support\Strings;

/**
 * Bruno's file-based format: one *.bru file per request. Each generated
 * file starts with a "# apisync:managed" marker line - the merge engine
 * only regenerates files that still carry it, so a developer who removes
 * the marker (or edits the file directly) opts that request out of
 * auto-sync without a separate config.
 */
final class BrunoExporter implements CollectionExporterInterface
{
    public const MANAGED_MARKER = '# apisync:managed';
    public const ENVIRONMENT_FILE = 'environments/environment.bru';

    public function name(): string
    {
        return 'bruno';
    }

    public function export(Collection $collection, ?string $environmentName = null): array
    {
        $files = [
            'bruno.json' => json_encode([
                'version' => '1',
                'name' => $collection->name,
                'type' => 'collection',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            self::ENVIRONMENT_FILE => $this->environment($collection->baseUrl),
        ];

        $seqByGroup = [];
        foreach ($collection->endpoints as $endpoint) {
            $seqByGroup[$endpoint->group] = ($seqByGroup[$endpoint->group] ?? 0) + 1;
            // kebabCase() assumes camelCase input and mangles an all-caps
            // run letter-by-letter (e.g. "GET-v1/foo" -> "g-e-t-v1/foo") -
            // lowercase the method-based fallback name upfront to avoid that.
            $fallbackName = strtolower($endpoint->method) . '-' . trim($endpoint->path, '/');
            $fileName = Strings::kebabCase(str_replace(' ', '-', $endpoint->name ?? $fallbackName));
            $group = str_replace('/', '-', $endpoint->group);
            $path = $group . '/' . $fileName . '.bru';
            $files[$path] = $this->requestFile($endpoint, $seqByGroup[$endpoint->group]);
        }

        return $files;
    }

    private function environment(?string $baseUrl): string
    {
        return implode("\n", [
            'vars {',
            '  baseUrl: ' . ($baseUrl ?? 'http://localhost'),
            '  authToken: ',
            '}',
        ]) . "\n";
    }

    private function requestFile(Endpoint $endpoint, int $seq): string
    {
        $pathParams = array_values(array_filter($endpoint->params, static fn (Param $p) => $p->in === Param::IN_PATH));
        $queryParams = array_values(array_filter($endpoint->params, static fn (Param $p) => $p->in === Param::IN_QUERY));
        $headerParams = array_values(array_filter($endpoint->params, static fn (Param $p) => $p->in === Param::IN_HEADER));
        $bodyParams = array_values(array_filter($endpoint->params, static fn (Param $p) => $p->in === Param::IN_BODY));
        $rawPath = preg_replace('/\{(\w+)\}/', ':$1', $endpoint->path) ?? $endpoint->path;

        $lines = [self::MANAGED_MARKER, ''];
        $lines[] = 'meta {';
        $lines[] = '  name: ' . ($endpoint->name ?? ($endpoint->method . ' ' . $endpoint->path));
        $lines[] = '  type: http';
        $lines[] = '  seq: ' . $seq;
        $lines[] = '}';
        $lines[] = '';

        $lines[] = strtolower($endpoint->method) . ' {';
        $lines[] = '  url: {{baseUrl}}' . $rawPath;
        $lines[] = '  body: ' . ($bodyParams !== [] ? 'json' : 'none');
        $lines[] = '  auth: ' . ($endpoint->auth ?? 'none');
        $lines[] = '}';

        if ($bodyParams !== []) {
            $lines[] = '';
            $lines[] = 'body:json {';
            foreach (explode("\n", BodyExample::json($bodyParams)) as $bodyLine) {
                $lines[] = '  ' . $bodyLine;
            }
            $lines[] = '}';
        }

        if ($pathParams !== []) {
            $lines[] = '';
            $lines[] = 'params:path {';
            foreach ($pathParams as $p) {
                $lines[] = '  ' . $p->name . ': ' . (string) ($p->example ?? '');
            }
            $lines[] = '}';
        }

        if ($queryParams !== []) {
            $lines[] = '';
            $lines[] = 'params:query {';
            foreach ($queryParams as $p) {
                $lines[] = '  ' . $p->name . ': ' . (string) ($p->example ?? '');
            }
            $lines[] = '}';
        }

        if ($headerParams !== []) {
            $lines[] = '';
            $lines[] = 'headers {';
            foreach ($headerParams as $p) {
                $lines[] = '  ' . $p->name . ': ' . (string) ($p->example ?? '');
            }
            $lines[] = '}';
        }

        if ($endpoint->description !== null) {
            $lines[] = '';
            $lines[] = 'docs {';
            $lines[] = '  ' . $endpoint->description;
            $lines[] = '}';
        }

        return implode("\n", $lines) . "\n";
    }
}
