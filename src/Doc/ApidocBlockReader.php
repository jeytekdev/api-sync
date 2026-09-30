<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Doc;

use Jeytekdev\ApiSync\Extractor\RouteCandidate;
use Jeytekdev\ApiSync\Ir\Param;
use Jeytekdev\ApiSync\Ir\Response;
use Jeytekdev\ApiSync\Support\ApidocDefineRegistry;
use Jeytekdev\ApiSync\Support\ApidocSyntax;

/**
 * Fallback doc source: parses apidoc.js-style tags (@api, @apiParam,
 * @apiSuccess, @apiHeader, @apiError, @apiGroup, @apiVersion,
 * @apiPermission, @apiName, @apiDescription, @apiUse) out of the method's
 * docblock. Used for any field an attribute doesn't already provide.
 */
final class ApidocBlockReader implements DocReaderInterface
{
    private const MAX_APIUSE_DEPTH = 5;

    public function __construct(private readonly ?ApidocDefineRegistry $defines = null)
    {
    }

    public function read(RouteCandidate $candidate): DocFragment
    {
        $doc = $candidate->docComment();
        if ($doc === null) {
            return new DocFragment();
        }

        $lines = $this->expandUses($this->tagLines($doc), 0);

        $name = null;
        $group = null;
        $version = null;
        $auth = null;
        $description = null;
        $params = [];
        $responses = [];
        /** @var array<int, list<string>> */
        $responseFields = [];

        foreach ($lines as [$tag, $rest]) {
            switch ($tag) {
                case 'apiName':
                    $name = trim($rest) ?: null;
                    break;
                case 'apiGroup':
                    $group = trim($rest) ?: null;
                    break;
                case 'apiVersion':
                    $version = trim($rest) ?: null;
                    break;
                case 'apiPermission':
                    $auth = trim($rest) ?: null;
                    break;
                case 'apiDescription':
                    $description = trim($rest) ?: $description;
                    break;
                case 'api':
                    $description ??= ApidocSyntax::parseApiTag($rest)['title'];
                    break;
                case 'apiParam':
                case 'apiHeader':
                    $field = $this->parseField($rest);
                    if ($field !== null) {
                        $params[] = new Param(
                            name: $field['name'],
                            in: $tag === 'apiHeader' ? Param::IN_HEADER : Param::IN_QUERY,
                            type: $field['type'],
                            required: $field['required'],
                            description: $field['description'],
                        );

                        // A documented "Authorization" header (often pulled in via
                        // @apiUse from a shared @apiDefine block) is the team's own
                        // record that auth is required - infer the scheme from its
                        // description so Scanner can fill in a usable {{authToken}}
                        // example instead of leaving the header value empty.
                        if ($tag === 'apiHeader' && strtolower($field['name']) === 'authorization') {
                            $auth ??= $this->authSchemeFromDescription($field['description'] ?? '');
                        }
                    }
                    break;
                case 'apiSuccess':
                case 'apiError':
                    $field = $this->parseField($rest);
                    if ($field !== null) {
                        $status = $field['group'] !== null && ctype_digit($field['group'])
                            ? (int) $field['group']
                            : ($tag === 'apiError' ? 400 : 200);
                        $responseFields[$status][] = trim($field['name'] . ($field['description'] !== null ? ': ' . $field['description'] : ''));
                    }
                    break;
            }
        }

        foreach ($responseFields as $status => $fields) {
            $responses[] = new Response($status, implode('; ', $fields));
        }

        return new DocFragment(
            group: $group,
            name: $name,
            version: $version,
            params: $params,
            auth: $auth,
            responses: $responses,
            description: $description,
        );
    }

    private function authSchemeFromDescription(string $description): ?string
    {
        if (preg_match('/\b(basic|bearer|digest)\b/i', $description, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    /**
     * Replaces every `@apiUse <Name>` tag with the tags of the matching
     * `@apiDefine <Name>` block (recursively, since a define block can
     * itself @apiUse another one) - apidoc.js define blocks are commonly
     * shared across many, unrelated files/actions, not physically
     * attached to any single one of them.
     *
     * @param list<array{0: string, 1: string}> $lines
     * @return list<array{0: string, 1: string}>
     */
    private function expandUses(array $lines, int $depth): array
    {
        if ($this->defines === null || $depth >= self::MAX_APIUSE_DEPTH) {
            return $lines;
        }

        $expanded = [];
        foreach ($lines as $line) {
            [$tag, $rest] = $line;
            if ($tag !== 'apiUse') {
                $expanded[] = $line;
                continue;
            }

            $block = $this->defines->get(trim($rest));
            if ($block !== null) {
                array_push($expanded, ...$this->expandUses($this->tagLines($block), $depth + 1));
            }
        }

        return $expanded;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function tagLines(string $doc): array
    {
        $tags = [];
        foreach (explode("\n", $doc) as $line) {
            $line = trim($line);
            $line = ltrim($line, "/* \t");
            if (!str_starts_with($line, '@')) {
                continue;
            }
            if (!preg_match('/^@(\w+)\s*(.*)$/', $line, $m)) {
                continue;
            }
            $tags[] = [$m[1], $m[2]];
        }

        return $tags;
    }

    /**
     * Parses "[(group)] [{type}] [name] description" as used by
     * @apiParam/@apiSuccess/@apiError/@apiHeader.
     *
     * @return array{name: string, type: string, required: bool, description: ?string, group: ?string}|null
     */
    private function parseField(string $rest): ?array
    {
        $rest = trim($rest);
        $group = null;

        if (preg_match('/^\(([^)]+)\)\s*(.*)$/', $rest, $m)) {
            $group = trim($m[1]);
            $rest = $m[2];
        }

        $type = 'string';
        if (preg_match('/^\{([^}]+)\}\s*(.*)$/', $rest, $m)) {
            $type = trim($m[1]);
            $rest = $m[2];
        }

        if (!preg_match('/^(\S+)\s*(.*)$/', trim($rest), $m)) {
            return null;
        }

        $rawName = $m[1];
        $description = trim($m[2]) ?: null;
        $required = !str_starts_with($rawName, '[');
        $name = trim($rawName, '[]');
        $name = explode('=', $name)[0];

        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'type' => $type,
            'required' => $required,
            'description' => $description,
            'group' => $group,
        ];
    }
}
