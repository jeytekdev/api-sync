<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Extractor;

use Jeytekdev\ApiSync\Support\AstFile;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;

/**
 * Recognizes Laravel-style route definitions ("Route::get('/path', ...)")
 * by statically parsing calls to a class named Route - no Laravel
 * bootstrap, no dependency on illuminate/routing being installed.
 */
final class LaravelPatternExtractor implements RouteExtractorInterface
{
    private const VERBS = ['get', 'post', 'put', 'patch', 'delete', 'options'];

    public function extract(AstFile $file): array
    {
        $candidates = [];

        foreach ($file->find(StaticCall::class) as $call) {
            if (!$call->class instanceof \PhpParser\Node\Name || $call->class->getLast() !== 'Route') {
                continue;
            }

            $verb = strtolower($call->name instanceof \PhpParser\Node\Identifier ? $call->name->toString() : '');
            if (!in_array($verb, self::VERBS, true)) {
                continue;
            }

            $pathArg = $call->getArgs()[0]->value ?? null;
            if (!$pathArg instanceof String_) {
                continue;
            }

            $group = $this->groupFromPath($pathArg->value);

            $candidates[] = new RouteCandidate(strtoupper($verb), $this->normalizePath($pathArg->value), $group, null, $call, $file->path);
        }

        return $candidates;
    }

    private function normalizePath(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    private function groupFromPath(string $path): string
    {
        $segment = explode('/', trim($path, '/'))[0] ?? 'default';

        return $segment === '' ? 'default' : $segment;
    }
}
