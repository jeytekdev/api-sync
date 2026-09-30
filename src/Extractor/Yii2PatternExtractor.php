<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Extractor;

use Jeytekdev\ApiSync\Support\AstFile;
use Jeytekdev\ApiSync\Support\Strings;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * Recognizes Yii2 controllers purely by naming convention (class name
 * ending in "Controller", methods named "actionXxx") - no dependency on
 * the yii2 framework being installed. When a Yii2Config is supplied, a
 * matching urlManager rule (RouteSource::UrlManager) is preferred over
 * the naming-convention guess (RouteSource::Convention) on a
 * per-action basis, and the controller's module prefix (e.g. "v1") is
 * auto-detected and applied to both.
 */
final class Yii2PatternExtractor implements RouteExtractorInterface
{
    /** @var array<string, string> action-name => HTTP method for common Yii2 REST conventions */
    private const REST_VERBS = [
        'index' => 'GET',
        'view' => 'GET',
        'create' => 'POST',
        'update' => 'PUT',
        'delete' => 'DELETE',
        'options' => 'OPTIONS',
    ];

    public function __construct(private readonly ?Yii2Config $urlManager = null)
    {
    }

    public function extract(AstFile $file): array
    {
        $candidates = [];

        foreach ($file->find(Class_::class) as $class) {
            $className = $class->name?->toString();
            if ($className === null || !str_ends_with($className, 'Controller')) {
                continue;
            }

            if ($this->extendsConsoleController($class)) {
                continue;
            }

            $controllerFqcn = $class->namespacedName?->toString();
            $controllerId = $this->controllerId($className, $controllerFqcn);
            $isRest = $this->extendsActiveController($class);

            foreach ($class->getMethods() as $method) {
                $methodName = $method->name->toString();
                if (!str_starts_with($methodName, 'action') || $methodName === 'actions') {
                    continue;
                }

                $actionId = Strings::kebabCase(substr($methodName, strlen('action')));
                $controllerRules = $this->urlManager?->rulesFor($controllerId) ?? [];
                $urlManagerRoute = $controllerRules[$actionId] ?? null;

                $candidates[] = $urlManagerRoute !== null
                    ? new RouteCandidate(
                        $urlManagerRoute['method'],
                        $urlManagerRoute['path'],
                        $controllerId,
                        null,
                        $method,
                        $file->path,
                        source: RouteSource::UrlManager,
                        controllerFqcn: $controllerFqcn,
                    )
                    : ($isRest
                        ? $this->restCandidate($method, $actionId, $controllerId, $controllerFqcn, $file->path)
                        : $this->webCandidate($method, $actionId, $controllerId, $controllerFqcn, $file->path));
            }
        }

        return $candidates;
    }

    private function controllerId(string $className, ?string $controllerFqcn): string
    {
        $bareId = Strings::kebabCase(Strings::stripSuffix($className, 'Controller'));

        if ($this->urlManager === null || $controllerFqcn === null) {
            return $bareId;
        }

        $namespace = $this->namespaceOf($controllerFqcn);
        $modulePrefix = $this->urlManager->modulePrefixFor($namespace);

        return $modulePrefix !== null ? $modulePrefix . '/' . $bareId : $bareId;
    }

    private function namespaceOf(string $fqcn): string
    {
        $parts = explode('\\', trim($fqcn, '\\'));
        array_pop($parts);

        return implode('\\', $parts);
    }

    /**
     * yii\rest\ActiveController-style: HTTP verb selects the action, the
     * action name never appears in the URL (e.g. GET /user/{id} -> view).
     */
    private function restCandidate(ClassMethod $method, string $actionId, string $controllerId, ?string $controllerFqcn, string $file): RouteCandidate
    {
        $httpMethod = self::REST_VERBS[$actionId] ?? 'GET';

        $path = '/' . $controllerId;
        if (in_array($actionId, ['view', 'update', 'delete'], true) && $this->hasIdParam($method)) {
            $path .= '/{id}';
        }

        return new RouteCandidate($httpMethod, $path, $controllerId, null, $method, $file, controllerFqcn: $controllerFqcn);
    }

    /**
     * Plain yii\web\Controller: the action name is part of the URL
     * ("/controller/action-name"), any extra scalar params are passed as
     * query string values by default Yii2 routing.
     */
    private function webCandidate(ClassMethod $method, string $actionId, string $controllerId, ?string $controllerFqcn, string $file): RouteCandidate
    {
        $path = '/' . $controllerId . ($actionId === 'index' ? '' : '/' . $actionId);

        return new RouteCandidate('GET', $path, $controllerId, null, $method, $file, controllerFqcn: $controllerFqcn);
    }

    private function extendsActiveController(Class_ $class): bool
    {
        return $class->extends !== null && str_contains(strtolower($class->extends->toString()), 'activecontroller');
    }

    /**
     * Console commands ("yii console/controllers", extending
     * yii\console\Controller) are not HTTP routes at all.
     */
    private function extendsConsoleController(Class_ $class): bool
    {
        return $class->extends !== null && str_contains(strtolower($class->extends->toString()), '\console\controller');
    }

    private function hasIdParam(ClassMethod $method): bool
    {
        foreach ($method->getParams() as $param) {
            if ($param->var instanceof \PhpParser\Node\Expr\Variable && $param->var->name === 'id') {
                return true;
            }
        }

        return false;
    }
}
