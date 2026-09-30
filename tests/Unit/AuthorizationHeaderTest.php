<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit;

use Jeytekdev\ApiSync\Doc\ApidocBlockReader;
use Jeytekdev\ApiSync\Doc\AttributeDocReader;
use Jeytekdev\ApiSync\Doc\Yii2AuthDocReader;
use Jeytekdev\ApiSync\Extractor\AttributeRouteExtractor;
use Jeytekdev\ApiSync\Extractor\Yii2Config;
use Jeytekdev\ApiSync\Extractor\Yii2PatternExtractor;
use Jeytekdev\ApiSync\Ir\Param;
use Jeytekdev\ApiSync\Scanner;
use Jeytekdev\ApiSync\Support\PsrAutoloadResolver;
use Jeytekdev\ApiSync\Support\Yii2AuthResolver;
use PHPUnit\Framework\TestCase;

final class AuthorizationHeaderTest extends TestCase
{
    private const PROJECT_ROOT = __DIR__ . '/../Yii2ProjectFixture';

    public function testEndpointsRequiringAuthGetAnAuthorizationHeaderReferencingAuthToken(): void
    {
        $resolver = PsrAutoloadResolver::forProjectRoot(self::PROJECT_ROOT);
        $yii2Config = Yii2Config::discover(self::PROJECT_ROOT, $resolver);

        $scanner = new Scanner(
            [new AttributeRouteExtractor(), new Yii2PatternExtractor($yii2Config)],
            [new Yii2AuthDocReader(new Yii2AuthResolver($resolver)), new ApidocBlockReader(), new AttributeDocReader()],
        );

        $endpoints = $scanner->scan([self::PROJECT_ROOT . '/src/api'], 'Test')->endpoints;

        self::assertNotEmpty($endpoints);
        foreach ($endpoints as $endpoint) {
            self::assertSame('basic', $endpoint->auth);

            $authHeader = current(array_filter(
                $endpoint->params,
                static fn (Param $p) => $p->in === Param::IN_HEADER && $p->name === 'Authorization',
            ));

            self::assertNotFalse($authHeader, 'Missing Authorization header for ' . $endpoint->method . ' ' . $endpoint->path);
            self::assertSame('Basic {{authToken}}', $authHeader->example);
            self::assertTrue($authHeader->required);
        }
    }
}
