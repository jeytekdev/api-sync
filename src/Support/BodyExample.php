<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Support;

use Jeytekdev\ApiSync\Ir\Param;

final class BodyExample
{
    /**
     * Builds a JSON example body object from body-in params, so the
     * request body isn't silently dropped by exporters.
     *
     * @param Param[] $bodyParams
     */
    public static function json(array $bodyParams): string
    {
        $example = [];
        foreach ($bodyParams as $param) {
            $example[$param->name] = $param->example ?? '';
        }

        return json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
