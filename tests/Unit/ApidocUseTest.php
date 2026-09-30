<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit;

use Jeytekdev\ApiSync\Doc\ApidocBlockReader;
use Jeytekdev\ApiSync\Doc\AttributeDocReader;
use Jeytekdev\ApiSync\Extractor\ApidocRouteExtractor;
use Jeytekdev\ApiSync\Extractor\AttributeRouteExtractor;
use Jeytekdev\ApiSync\Extractor\LaravelPatternExtractor;
use Jeytekdev\ApiSync\Extractor\Yii2PatternExtractor;
use Jeytekdev\ApiSync\Ir\Endpoint;
use Jeytekdev\ApiSync\Ir\Param;
use Jeytekdev\ApiSync\Scanner;
use Jeytekdev\ApiSync\Support\ApidocDefineRegistry;
use PHPUnit\Framework\TestCase;

final class ApidocUseTest extends TestCase
{
    public function testApiUseResolvesAnApiDefineBlockFromACompletelyDifferentFile(): void
    {
        $defines = new ApidocDefineRegistry();
        $scanner = new Scanner(
            [new AttributeRouteExtractor(), new ApidocRouteExtractor(), new Yii2PatternExtractor(), new LaravelPatternExtractor()],
            [new ApidocBlockReader($defines), new AttributeDocReader()],
            $defines,
        );

        // ApiDefines.php (holding @apiDefine HeaderRequest) and
        // SmsController.php (using @apiUse HeaderRequest) are unrelated
        // files, scanned in the same run.
        $endpoints = $scanner->scan([__DIR__ . '/../Fixtures/Yii2'], 'Test')->endpoints;

        $sms = current(array_filter(
            $endpoints,
            static fn (Endpoint $e) => str_contains($e->sourceFile ?? '', 'SmsController.php'),
        ));

        self::assertNotFalse($sms);
        self::assertSame('basic', $sms->auth);

        $authHeader = current(array_filter(
            $sms->params,
            static fn (Param $p) => $p->in === Param::IN_HEADER && $p->name === 'Authorization',
        ));

        self::assertNotFalse($authHeader);
        self::assertSame('Basic {{authToken}}', $authHeader->example);
        self::assertStringContainsString('login:password', $authHeader->description);

        // @apiUse must not leak into the endpoint's other params.
        $phoneParam = current(array_filter($sms->params, static fn (Param $p) => $p->name === 'phone'));
        self::assertNotFalse($phoneParam);
    }
}
