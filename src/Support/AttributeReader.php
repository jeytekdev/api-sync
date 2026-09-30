<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Support;

use PhpParser\Node;
use PhpParser\Node\Attribute;

/**
 * Reads PHP 8 attributes straight from the AST (no reflection, no
 * autoloading of the attribute classes themselves).
 */
final class AttributeReader
{
    /**
     * @return Attribute[]
     */
    public static function attributesOn(Node $node, string $shortNameSuffix = ''): array
    {
        if (!property_exists($node, 'attrGroups')) {
            return [];
        }

        $found = [];
        /** @var \PhpParser\Node\AttributeGroup $group */
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $name = $attr->name->toString();
                if ($shortNameSuffix === '' || str_ends_with($name, $shortNameSuffix)) {
                    $found[] = $attr;
                }
            }
        }

        return $found;
    }

    public static function shortName(Attribute $attr): string
    {
        $parts = explode('\\', $attr->name->toString());

        return end($parts);
    }

    /**
     * Resolves an attribute's arguments into a plain array, keyed by
     * argument name when named, or by position otherwise. Only literal
     * scalar/array expressions are resolved (constants/expressions are
     * skipped) since we never execute target-project code.
     *
     * @return array<int|string, mixed>
     */
    public static function args(Attribute $attr): array
    {
        $resolved = [];
        foreach ($attr->args as $position => $arg) {
            $key = $arg->name?->toString() ?? $position;
            $resolved[$key] = PhpLiteralEvaluator::evaluate($arg->value);
        }

        return $resolved;
    }
}
