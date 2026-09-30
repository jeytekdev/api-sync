<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Support;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar;

/**
 * Resolves a literal PHP expression (scalars, arrays, `::class` constants,
 * true/false/null) into a plain PHP value, without executing anything.
 * Used both to read attribute arguments and to statically evaluate config
 * files (e.g. a `urlManager`/`modules` array) - any non-literal expression
 * (variables, function calls, ternaries, ...) simply resolves to null,
 * since we never run the target project's code.
 */
final class PhpLiteralEvaluator
{
    public static function evaluate(Expr $expr): mixed
    {
        return match (true) {
            $expr instanceof Scalar\String_ => $expr->value,
            $expr instanceof Scalar\Int_, $expr instanceof Scalar\Float_ => $expr->value,
            $expr instanceof Expr\ConstFetch => match (strtolower($expr->name->toString())) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => $expr->name->toString(),
            },
            $expr instanceof Expr\ClassConstFetch => self::evaluateClassConstFetch($expr),
            $expr instanceof Expr\Array_ => self::evaluateArray($expr),
            default => null,
        };
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function evaluateArray(Expr\Array_ $array): array
    {
        $items = [];
        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }
            $value = self::evaluate($item->value);
            $key = $item->key !== null ? self::evaluate($item->key) : null;
            if (is_string($key) || is_int($key)) {
                $items[$key] = $value;
            } else {
                // Key isn't statically resolvable (e.g. a constant/expression
                // we don't evaluate) - append rather than coerce it into a
                // null/empty-string key.
                $items[] = $value;
            }
        }

        return $items;
    }

    /**
     * "Foo\Bar::class" -> "Foo\Bar" (already fully resolved by NameResolver).
     * Any other class constant (e.g. "Foo::SOME_CONST") is not resolvable
     * statically and returns null.
     */
    private static function evaluateClassConstFetch(Expr\ClassConstFetch $expr): ?string
    {
        if (!$expr->name instanceof Identifier || strtolower($expr->name->toString()) !== 'class') {
            return null;
        }

        return $expr->class instanceof \PhpParser\Node\Name ? $expr->class->toString() : null;
    }
}
