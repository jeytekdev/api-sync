<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Config;

final class Config
{
    /**
     * @param string[] $source
     * @param string[] $formats
     */
    public function __construct(
        public readonly array $source,
        public readonly string $name = 'API',
        public readonly ?string $baseUrl = null,
        public readonly string $out = './api-collections',
        public readonly array $formats = ['postman', 'insomnia', 'bruno'],
        public readonly bool $prune = false,
        public readonly ?string $environmentName = null,
        public readonly string $projectRoot = '.',
    ) {
    }

    public static function fromFile(string $path): self
    {
        $data = is_file($path) ? (require $path) : [];
        if (!is_array($data)) {
            $data = [];
        }

        $projectRoot = is_file($path) ? dirname((string) realpath($path)) : getcwd();

        return new self(
            source: $data['source'] ?? ['./src'],
            name: $data['name'] ?? 'API',
            baseUrl: $data['baseUrl'] ?? null,
            out: $data['out'] ?? './api-collections',
            formats: $data['formats'] ?? ['postman', 'insomnia', 'bruno'],
            prune: $data['prune'] ?? false,
            environmentName: $data['environmentName'] ?? null,
            projectRoot: $projectRoot !== false ? $projectRoot : '.',
        );
    }

    public function withOverrides(?array $source, ?string $name, ?string $baseUrl, ?string $out, ?array $formats, ?bool $prune): self
    {
        return new self(
            source: $source ?? $this->source,
            name: $name ?? $this->name,
            baseUrl: $baseUrl ?? $this->baseUrl,
            out: $out ?? $this->out,
            formats: $formats ?? $this->formats,
            prune: $prune ?? $this->prune,
            environmentName: $this->environmentName,
            projectRoot: $this->projectRoot,
        );
    }
}
