<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Support;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;

/**
 * Infers a controller's auth scheme by statically walking its class
 * hierarchy (via PsrAutoloadResolver) looking for a `behaviors()` method
 * that declares an `'authenticator' => [...]` filter - the standard Yii2
 * pattern, often defined once on a shared base controller rather than
 * redeclared per-controller.
 */
final class Yii2AuthResolver
{
    private const SCHEME_BY_CLASS_SUFFIX = [
        'HttpBasicAuth' => 'basic',
        'HttpBearerAuth' => 'bearer',
        'HttpDigestAuth' => 'digest',
        'QueryParamAuth' => 'query',
        'CompositeAuth' => 'composite',
    ];

    private const MAX_DEPTH = 6;

    /** @var array<string, string|null> */
    private array $cache = [];

    public function __construct(private readonly ?PsrAutoloadResolver $resolver)
    {
    }

    public function authFor(string $fqcn, int $depth = 0): ?string
    {
        $fqcn = ltrim($fqcn, '\\');
        if (array_key_exists($fqcn, $this->cache)) {
            return $this->cache[$fqcn];
        }
        if ($depth >= self::MAX_DEPTH || $this->resolver === null) {
            return $this->cache[$fqcn] = null;
        }

        $path = $this->resolver->resolve($fqcn);
        if ($path === null) {
            return $this->cache[$fqcn] = null;
        }

        $astFile = AstFile::parse($path);
        if ($astFile === null) {
            return $this->cache[$fqcn] = null;
        }

        $class = $this->findClass($astFile, $fqcn);
        if ($class === null) {
            return $this->cache[$fqcn] = null;
        }

        $scheme = $this->authFromBehaviors($class);
        if ($scheme === null && $class->extends !== null) {
            $scheme = $this->authFor($class->extends->toString(), $depth + 1);
        }

        return $this->cache[$fqcn] = $scheme;
    }

    private function findClass(AstFile $astFile, string $fqcn): ?Class_
    {
        foreach ($astFile->find(Class_::class) as $class) {
            if ($class->namespacedName?->toString() === $fqcn) {
                return $class;
            }
        }

        return null;
    }

    private function authFromBehaviors(Class_ $class): ?string
    {
        foreach ($class->getMethods() as $method) {
            if ($method->name->toString() === 'behaviors') {
                return $this->authFromBehaviorsMethod($method);
            }
        }

        return null;
    }

    private function authFromBehaviorsMethod(ClassMethod $method): ?string
    {
        foreach ($method->getStmts() ?? [] as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr !== null) {
                $scheme = $this->searchExprForAuthenticator($stmt->expr);
                if ($scheme !== null) {
                    return $scheme;
                }
            }
        }

        return null;
    }

    /**
     * Recursively searches an expression (array literal, or a function
     * call whose arguments contain array literals - e.g.
     * `ArrayHelper::merge(parent::behaviors(), [...])`) for an
     * `'authenticator' => [...]` entry.
     */
    private function searchExprForAuthenticator(mixed $expr): ?string
    {
        if ($expr instanceof \PhpParser\Node\Expr\Array_) {
            foreach ($expr->items as $item) {
                if (!$item instanceof ArrayItem || $item->key === null) {
                    continue;
                }
                $key = PhpLiteralEvaluator::evaluate($item->key);
                if ($key === 'authenticator' && $item->value instanceof \PhpParser\Node\Expr\Array_) {
                    $scheme = $this->schemeFromAuthenticatorArray($item->value);
                    if ($scheme !== null) {
                        return $scheme;
                    }
                }
            }

            foreach ($expr->items as $item) {
                if ($item instanceof ArrayItem) {
                    $scheme = $this->searchExprForAuthenticator($item->value);
                    if ($scheme !== null) {
                        return $scheme;
                    }
                }
            }

            return null;
        }

        if ($expr instanceof StaticCall || $expr instanceof MethodCall) {
            foreach ($expr->getArgs() as $arg) {
                if ($arg instanceof Arg) {
                    $scheme = $this->searchExprForAuthenticator($arg->value);
                    if ($scheme !== null) {
                        return $scheme;
                    }
                }
            }
        }

        return null;
    }

    private function schemeFromAuthenticatorArray(\PhpParser\Node\Expr\Array_ $array): ?string
    {
        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem || $item->key === null) {
                continue;
            }
            if (PhpLiteralEvaluator::evaluate($item->key) !== 'class') {
                continue;
            }

            $class = $this->classNameFromExpr($item->value);
            if (!is_string($class)) {
                continue;
            }

            foreach (self::SCHEME_BY_CLASS_SUFFIX as $suffix => $scheme) {
                if (str_ends_with($class, $suffix)) {
                    return $scheme;
                }
            }
        }

        return null;
    }

    /**
     * Resolves either the native "Foo::class" constant or Yii2's legacy
     * "Foo::className()" static helper (still used in this codebase) to
     * the class's FQCN.
     */
    private function classNameFromExpr(mixed $expr): ?string
    {
        if ($expr instanceof ClassConstFetch) {
            $value = PhpLiteralEvaluator::evaluate($expr);

            return is_string($value) ? $value : null;
        }

        if (
            $expr instanceof StaticCall
            && $expr->name instanceof \PhpParser\Node\Identifier
            && strtolower($expr->name->toString()) === 'classname'
            && $expr->class instanceof \PhpParser\Node\Name
        ) {
            return $expr->class->toString();
        }

        return null;
    }
}
