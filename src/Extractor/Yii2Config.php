<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Extractor;

use Jeytekdev\ApiSync\Support\AstFile;
use Jeytekdev\ApiSync\Support\PhpLiteralEvaluator;
use Jeytekdev\ApiSync\Support\PsrAutoloadResolver;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Property;

/**
 * Statically resolves Yii2 `urlManager.rules` (both plain string rules and
 * `yii\rest\UrlRule` REST rules: extraPatterns/except/only/tokens/pluralize)
 * and `modules` module-prefix registration - no manual config path needed.
 * App config files are discovered under the hood (project root `config/`
 * for a basic-template app, `<entry-point>/config/` for an advanced one),
 * and a `urlManager` value that's itself `require`'d from a separate file
 * (e.g. `require __DIR__ . '/url-manager.php'`) is followed automatically.
 * Nothing is executed - everything is parsed as a plain literal array.
 */
final class Yii2Config
{
    private const DEFAULT_CRUD = [
        'index' => ['GET', ''],
        'view' => ['GET', '/{id}'],
        'create' => ['POST', ''],
        'update' => ['PUT', '/{id}'],
        'delete' => ['DELETE', '/{id}'],
    ];

    /** Top-level directories never worth descending into looking for a config/ subdirectory. */
    private const SKIP_TOP_LEVEL_DIRS = ['vendor', 'node_modules', '.git', 'runtime', 'storage', 'tests', 'test', 'migrations'];

    /** @var array<string, array<string, array{method: string, path: string}>> controllerId => actionId => route */
    private array $rules = [];

    /** @var array<string, array<string, mixed>> moduleId => raw module config */
    private array $modules = [];

    /** @var array<string, ?string> moduleId => resolved controller namespace (cached) */
    private array $moduleNamespaceCache = [];

    private function __construct(private readonly ?PsrAutoloadResolver $classResolver)
    {
    }

    public static function discover(string $projectRoot, ?PsrAutoloadResolver $classResolver = null): self
    {
        $config = new self($classResolver);

        foreach (self::discoverConfigFiles($projectRoot) as $path) {
            $config->collectFromFile($path);
        }

        return $config;
    }

    /**
     * @return array<string, array{method: string, path: string}>
     */
    public function rulesFor(string $controllerId): array
    {
        return $this->rules[$controllerId] ?? [];
    }

    /**
     * Returns the module ID (e.g. "v1") whose resolved controller
     * namespace matches or prefixes the given controller namespace, or
     * null if no configured module applies.
     */
    public function modulePrefixFor(string $namespace): ?string
    {
        foreach ($this->modules as $moduleId => $moduleConfig) {
            $controllerNamespace = $this->controllerNamespaceForModule((string) $moduleId, $moduleConfig);
            if ($controllerNamespace === null) {
                continue;
            }

            if ($namespace === $controllerNamespace || str_starts_with($namespace, $controllerNamespace . '\\')) {
                return (string) $moduleId;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private static function discoverConfigFiles(string $projectRoot): array
    {
        $root = rtrim($projectRoot, '/');
        if ($root === '' || !is_dir($root)) {
            return [];
        }

        $configDirs = [];
        if (is_dir($root . '/config')) {
            $configDirs[] = $root . '/config';
        }

        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, self::SKIP_TOP_LEVEL_DIRS, true)) {
                continue;
            }
            $candidate = $root . '/' . $entry . '/config';
            if (is_dir($candidate)) {
                $configDirs[] = $candidate;
            }
        }

        $files = [];
        foreach ($configDirs as $dir) {
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function collectFromFile(string $path): void
    {
        $astFile = AstFile::parse($path);
        if ($astFile === null) {
            return;
        }

        $topExpr = $astFile->topLevelReturnExpr();
        if (!$topExpr instanceof Array_) {
            return;
        }

        $urlManagerExpr = $this->findArrayValueExpr($topExpr, ['components', 'urlManager'])
            ?? $this->findArrayValueExpr($topExpr, ['urlManager']);

        if ($urlManagerExpr !== null) {
            $urlManagerData = $this->evaluateMaybeRequire($urlManagerExpr, dirname($path));
            if (is_array($urlManagerData)) {
                $this->collectRules($this->extractRulesList($urlManagerData));
            }
        }

        $data = PhpLiteralEvaluator::evaluateArray($topExpr);

        // A file discovered on its own (e.g. "config/url-manager.php", not
        // wrapped in a 'components'/'urlManager' key at all) is treated as
        // urlManager config directly when its name says so - matching it
        // by content shape alone would risk misreading an unrelated config
        // file (db.php, params.php, ...) as a list of routes.
        if ($urlManagerExpr === null && $this->looksLikeUrlManagerFile($path)) {
            $this->collectRules($this->extractRulesList($data));
        }

        $this->collectModules($data);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function extractRulesList(array $data): array
    {
        if (isset($data['rules']) && is_array($data['rules'])) {
            return $data['rules'];
        }

        return $data;
    }

    private function looksLikeUrlManagerFile(string $path): bool
    {
        return (bool) preg_match('/url[-_]?(manager|rules)/i', basename($path));
    }

    /**
     * Evaluates a urlManager value that may itself be `require`'d from a
     * separate file (`'urlManager' => require __DIR__ . '/url-manager.php'`)
     * instead of an inline array literal.
     */
    private function evaluateMaybeRequire(Expr $expr, string $currentDir): mixed
    {
        if ($expr instanceof Include_) {
            $path = $this->resolveIncludePath($expr->expr, $currentDir);
            if ($path === null || !is_file($path)) {
                return null;
            }

            $requiredExpr = AstFile::parse($path)?->topLevelReturnExpr();

            return $requiredExpr !== null ? PhpLiteralEvaluator::evaluate($requiredExpr) : null;
        }

        return PhpLiteralEvaluator::evaluate($expr);
    }

    private function resolveIncludePath(Expr $expr, string $currentDir): ?string
    {
        $resolved = $this->resolveStringExpr($expr, $currentDir);
        if ($resolved === null) {
            return null;
        }

        return str_starts_with($resolved, '/') ? $resolved : $currentDir . '/' . $resolved;
    }

    /**
     * Resolves __DIR__, dirname(...) and string concatenation - the
     * handful of expression shapes Yii2 config files actually use to
     * build a `require` path. Anything else (variables, aliases like
     * "@common/...", function calls other than dirname()) isn't
     * statically resolvable and returns null.
     */
    private function resolveStringExpr(Expr $expr, string $currentDir): ?string
    {
        if ($expr instanceof Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Scalar\MagicConst\Dir) {
            return $currentDir;
        }

        if ($expr instanceof Concat) {
            $left = $this->resolveStringExpr($expr->left, $currentDir);
            $right = $this->resolveStringExpr($expr->right, $currentDir);

            return ($left !== null && $right !== null) ? $left . $right : null;
        }

        if ($expr instanceof FuncCall && $expr->name instanceof Name && strtolower($expr->name->toString()) === 'dirname') {
            $args = $expr->getArgs();
            $inner = isset($args[0]) ? $this->resolveStringExpr($args[0]->value, $currentDir) : null;

            return $inner !== null ? dirname($inner) : null;
        }

        return null;
    }

    /**
     * @param Expr $expr an already-resolved Array_ subtree lookup path
     */
    private function findArrayValueExpr(Expr $expr, array $path): ?Expr
    {
        if ($path === []) {
            return $expr;
        }
        if (!$expr instanceof Array_) {
            return null;
        }

        $key = array_shift($path);
        foreach ($expr->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }
            if (PhpLiteralEvaluator::evaluate($item->key) === $key) {
                return $this->findArrayValueExpr($item->value, $path);
            }
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $rules
     */
    private function collectRules(array $rules): void
    {
        foreach ($rules as $key => $rule) {
            if (is_string($rule)) {
                $this->applyStringRule((string) $key, $rule);
                continue;
            }

            if (!is_array($rule)) {
                continue;
            }

            $class = $rule['class'] ?? null;
            if (is_string($class) && (
                $class === 'yii\rest\UrlRule'
                || str_ends_with($class, '\rest\UrlRule')
            )) {
                $this->applyRestRule($rule);
            } elseif (isset($rule['pattern'], $rule['route']) && is_string($rule['pattern']) && is_string($rule['route'])) {
                $this->applyStringRule($rule['pattern'], $rule['route']);
            }
        }
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function collectModules(array $data): void
    {
        $modules = $data['modules'] ?? null;
        if (!is_array($modules)) {
            return;
        }

        foreach ($modules as $moduleId => $moduleConfig) {
            if (is_string($moduleId) && is_array($moduleConfig)) {
                $this->modules[$moduleId] = $moduleConfig;
            }
        }
    }

    private function applyStringRule(string $patternSpec, string $route): void
    {
        [$verb, $pattern] = $this->splitPatternKey($patternSpec);

        $routeParts = explode('/', trim($route, '/'));
        $actionId = array_pop($routeParts);
        $controllerId = implode('/', $routeParts);
        if ($controllerId === '' || $actionId === '') {
            return;
        }

        $path = '/' . ltrim($this->normalizeUrlParams($pattern), '/');
        $this->rules[$controllerId][$actionId] = ['method' => $verb, 'path' => $path];
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function applyRestRule(array $rule): void
    {
        $controllers = $rule['controller'] ?? [];
        if (is_string($controllers)) {
            $controllers = [$controllers];
        }
        if (!is_array($controllers)) {
            return;
        }

        $except = is_array($rule['except'] ?? null) ? array_values(array_filter($rule['except'], 'is_string')) : [];
        $only = is_array($rule['only'] ?? null) ? array_values(array_filter($rule['only'], 'is_string')) : null;
        $pluralize = ($rule['pluralize'] ?? true) !== false;
        $extraPatterns = is_array($rule['extraPatterns'] ?? null) ? $rule['extraPatterns'] : [];

        foreach ($controllers as $controllerId) {
            if (!is_string($controllerId)) {
                continue;
            }

            $urlSegment = $this->pluralizeSegment($controllerId, $pluralize);
            $actions = [];

            foreach (self::DEFAULT_CRUD as $actionId => [$method, $suffix]) {
                if (in_array($actionId, $except, true)) {
                    continue;
                }
                if ($only !== null && !in_array($actionId, $only, true)) {
                    continue;
                }
                $actions[$actionId] = ['method' => $method, 'path' => '/' . $urlSegment . $suffix];
            }

            foreach ($extraPatterns as $patternKey => $actionId) {
                if (!is_string($actionId) || !is_string($patternKey)) {
                    continue;
                }
                [$verb, $pattern] = $this->splitPatternKey($patternKey);
                $suffix = $pattern !== '' ? '/' . $this->normalizeUrlParams($pattern) : '';
                $actions[$actionId] = ['method' => $verb, 'path' => '/' . $urlSegment . $suffix];
            }

            if ($actions !== []) {
                $this->rules[$controllerId] = array_merge($this->rules[$controllerId] ?? [], $actions);
            }
        }
    }

    /**
     * @return array{0: string, 1: string} [verb, pattern] - verb defaults to GET when the key has no verb prefix.
     */
    private function splitPatternKey(string $key): array
    {
        $key = trim($key);
        if (preg_match('/^([A-Z]+)(?:\s+(.*))?$/', $key, $m)) {
            return [$m[1], trim($m[2] ?? '')];
        }

        return ['GET', $key];
    }

    /**
     * Yii2 URL rule tokens use "<name>" or "<name:regex>"; this package's
     * IR/exporters use "{name}".
     */
    private function normalizeUrlParams(string $pattern): string
    {
        return preg_replace('/<(\w+)(:[^>]+)?>/', '{$1}', $pattern) ?? $pattern;
    }

    private function pluralizeSegment(string $controllerId, bool $pluralize): string
    {
        if (!$pluralize) {
            return $controllerId;
        }

        $parts = explode('/', $controllerId);
        $last = array_pop($parts);
        $parts[] = $this->naivePluralize($last);

        return implode('/', $parts);
    }

    /**
     * Deliberately conservative pluralization: append "s", unless the
     * word already ends in "s" (common for hyphenated multi-word or
     * acronym-like controller IDs - e.g. "push-messages", "sms" - where
     * Yii2's real inflector rules are impossible to replicate exactly
     * without a full dictionary, and guessing "pushmessageses" is worse
     * than under-pluralizing). Projects that need exact control should
     * set `'pluralize' => false` on the rule, same as they would for
     * Yii2's own inflector.
     */
    private function naivePluralize(string $word): string
    {
        if ($word === '' || str_ends_with($word, 's')) {
            return $word;
        }

        return $word . 's';
    }

    /**
     * @param array<string, mixed> $moduleConfig
     */
    private function controllerNamespaceForModule(string $moduleId, array $moduleConfig): ?string
    {
        if (array_key_exists($moduleId, $this->moduleNamespaceCache)) {
            return $this->moduleNamespaceCache[$moduleId];
        }

        $class = $moduleConfig['class'] ?? null;
        $result = is_string($class)
            ? ($this->explicitControllerNamespace($class) ?? $this->namespaceOf($class))
            : null;

        return $this->moduleNamespaceCache[$moduleId] = $result;
    }

    private function explicitControllerNamespace(string $moduleClassFqcn): ?string
    {
        if ($this->classResolver === null) {
            return null;
        }

        $path = $this->classResolver->resolve($moduleClassFqcn);
        if ($path === null) {
            return null;
        }

        $astFile = AstFile::parse($path);
        if ($astFile === null) {
            return null;
        }

        foreach ($astFile->find(Property::class) as $property) {
            foreach ($property->props as $prop) {
                if ($prop->name->toString() !== 'controllerNamespace' || $prop->default === null) {
                    continue;
                }
                $value = PhpLiteralEvaluator::evaluate($prop->default);
                if (is_string($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function namespaceOf(string $fqcn): string
    {
        $parts = explode('\\', trim($fqcn, '\\'));
        array_pop($parts);

        return implode('\\', $parts);
    }
}
