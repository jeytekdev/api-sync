<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync;

use Jeytekdev\ApiSync\Doc\DocFragment;
use Jeytekdev\ApiSync\Doc\DocReaderInterface;
use Jeytekdev\ApiSync\Extractor\RouteCandidate;
use Jeytekdev\ApiSync\Extractor\RouteExtractorInterface;
use Jeytekdev\ApiSync\Extractor\RouteSource;
use Jeytekdev\ApiSync\Ir\Collection;
use Jeytekdev\ApiSync\Ir\Endpoint;
use Jeytekdev\ApiSync\Ir\Param;
use Jeytekdev\ApiSync\Support\ApidocDefineRegistry;
use Jeytekdev\ApiSync\Support\ApidocSyntax;
use Jeytekdev\ApiSync\Support\AstFile;
use Symfony\Component\Finder\Finder;

/**
 * Orchestrates the whole pipeline: find PHP files -> run route extractors
 * -> per file, drop naming-convention guesses if the same file also has a
 * free-standing apidoc.js `@api` block (real codebases routinely document
 * a controller's actions as one header block, decoupled from any single
 * method, which a guess can't compete with) -> enrich each surviving
 * route with doc readers (lowest priority first, so later readers
 * override earlier ones field-by-field) -> build the IR Collection.
 */
final class Scanner
{
    /**
     * @param RouteExtractorInterface[] $extractors
     * @param DocReaderInterface[] $docReaders ordered lowest to highest priority
     */
    public function __construct(
        private readonly array $extractors,
        private readonly array $docReaders,
        private readonly ?ApidocDefineRegistry $defineRegistry = null,
    ) {
    }

    /**
     * @param string[] $sourcePaths
     */
    public function scan(array $sourcePaths, string $collectionName, ?string $baseUrl = null): Collection
    {
        // First pass: parse every file once and register any @apiDefine
        // block found anywhere, so @apiUse can resolve regardless of which
        // file is processed first - apidoc.js define blocks are commonly
        // shared across many, unrelated files.
        $astFiles = [];
        foreach ($this->phpFiles($sourcePaths) as $path) {
            $astFile = AstFile::parse($path);
            if ($astFile === null) {
                continue;
            }
            $astFiles[$path] = $astFile;
            $this->registerApidocDefines($astFile);
        }

        $endpoints = [];

        foreach ($astFiles as $path => $astFile) {
            $candidates = [];
            foreach ($this->extractors as $extractor) {
                array_push($candidates, ...$extractor->extract($astFile));
            }

            foreach ($this->keepFor($candidates) as $candidate) {
                $fragment = new DocFragment();
                foreach ($this->docReaders as $reader) {
                    $fragment = $reader->read($candidate)->mergeOver($fragment);
                }

                $endpoints[] = new Endpoint(
                    method: $candidate->httpMethod,
                    path: $candidate->path,
                    group: $fragment->group ?? $candidate->group,
                    name: $fragment->name ?? $candidate->name,
                    version: $fragment->version,
                    params: $this->withAuthorizationHeader($fragment->params, $fragment->auth),
                    auth: $fragment->auth,
                    responses: $fragment->responses,
                    description: $fragment->description,
                    sourceFile: $path,
                );
            }
        }

        return new Collection($collectionName, $endpoints, $baseUrl);
    }

    private function registerApidocDefines(AstFile $astFile): void
    {
        if ($this->defineRegistry === null) {
            return;
        }

        foreach ($astFile->docCommentBlocks() as $block) {
            $name = ApidocSyntax::firstApiDefineNameIn($block);
            if ($name !== null) {
                $this->defineRegistry->add($name, $block);
            }
        }
    }

    /**
     * Adds an Authorization header param when the endpoint requires auth
     * and one wasn't already documented explicitly. All exporters render
     * IN_HEADER params uniformly, so this alone is enough to get the
     * header in Postman/Insomnia/Bruno without touching any exporter.
     *
     * @param Param[] $params
     * @return Param[]
     */
    private function withAuthorizationHeader(array $params, ?string $auth): array
    {
        if ($auth === null) {
            return $params;
        }

        $example = match ($auth) {
            'basic' => 'Basic {{authToken}}',
            'bearer' => 'Bearer {{authToken}}',
            default => '{{authToken}}',
        };

        foreach ($params as $i => $param) {
            if ($param->in !== Param::IN_HEADER || strtolower($param->name) !== 'authorization') {
                continue;
            }

            // Already documented (e.g. via apidoc's @apiHeader Authorization)
            // but with no usable value - fill one in, keep everything else.
            if ($param->example === null || $param->example === '') {
                $params[$i] = new Param($param->name, $param->in, $param->type, $param->required, $param->description, $example);
            }

            return $params;
        }

        $params[] = new Param('Authorization', Param::IN_HEADER, required: true, example: $example);

        return $params;
    }

    /**
     * @param RouteCandidate[] $candidates all candidates found in one file
     * @return RouteCandidate[]
     */
    private function keepFor(array $candidates): array
    {
        foreach ($candidates as $candidate) {
            if ($candidate->source === RouteSource::ApidocBlock) {
                // This file documents its routes via apidoc.js blocks - trust
                // those over both a naming-convention guess AND a urlManager
                // match for the same file. apidoc carries far richer info
                // (params, descriptions, headers) than a bare method+path
                // resolved from urlManager, so showing both would just add a
                // sparse duplicate next to the real, documented entry.
                return array_values(array_filter(
                    $candidates,
                    static fn (RouteCandidate $c) => $c->source === RouteSource::ApidocBlock || $c->source === RouteSource::Attribute,
                ));
            }
        }

        return $candidates;
    }

    private const EXCLUDED_DIRS = ['vendor', 'node_modules', 'runtime', 'storage'];

    /**
     * Files larger than this are never route/controller source - almost
     * always vendored/generated code that slipped in through a broad
     * `source` path. Skipping them avoids feeding pathological files
     * (some generated SDKs run into tens of MB per file) to the parser,
     * which can otherwise exhaust PHP's memory limit.
     */
    private const MAX_FILE_SIZE = 2 * 1024 * 1024;

    /**
     * @param string[] $sourcePaths
     * @return string[]
     */
    private function phpFiles(array $sourcePaths): array
    {
        $finder = new Finder();
        $existing = array_filter($sourcePaths, static fn (string $p) => file_exists($p));
        if ($existing === []) {
            return [];
        }

        $finder->files()->in($existing)->name('*.php')->exclude(self::EXCLUDED_DIRS)->size('<= ' . self::MAX_FILE_SIZE);

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        sort($files);

        return $files;
    }
}
