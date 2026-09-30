<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Support;

final class Strings
{
    public static function kebabCase(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '-$0', $value) ?? $value;

        return strtolower($value);
    }

    public static function stripSuffix(string $value, string $suffix): string
    {
        return str_ends_with($value, $suffix) ? substr($value, 0, -strlen($suffix)) : $value;
    }

    public static function classShortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
