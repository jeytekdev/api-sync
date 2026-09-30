<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit;

use Jeytekdev\ApiSync\Doc\ApidocBlockReader;
use Jeytekdev\ApiSync\Doc\AttributeDocReader;
use Jeytekdev\ApiSync\Extractor\ApidocRouteExtractor;
use Jeytekdev\ApiSync\Extractor\AttributeRouteExtractor;
use Jeytekdev\ApiSync\Extractor\Yii2Config;
use Jeytekdev\ApiSync\Extractor\Yii2PatternExtractor;
use Jeytekdev\ApiSync\Ir\Endpoint;
use Jeytekdev\ApiSync\Scanner;
use Jeytekdev\ApiSync\Support\PsrAutoloadResolver;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for a real bug found running against notificator: a
 * controller documented with an apidoc.js @api block that ALSO matches a
 * urlManager rule produced two endpoints for the same action - the rich,
 * documented one and a sparse, wrongly-pluralized duplicate.
 */
final class ScannerDedupTest extends TestCase
{
    private const PROJECT_ROOT = __DIR__ . '/../Yii2ProjectFixture';

    public function testApidocBlockWinsOverAMatchingUrlManagerRuleInsteadOfDuplicating(): void
    {
        $resolver = PsrAutoloadResolver::forProjectRoot(self::PROJECT_ROOT);
        $yii2Config = Yii2Config::discover(self::PROJECT_ROOT, $resolver);

        $scanner = new Scanner(
            [new AttributeRouteExtractor(), new ApidocRouteExtractor(), new Yii2PatternExtractor($yii2Config)],
            [new ApidocBlockReader(), new AttributeDocReader()],
        );

        $endpoints = $scanner->scan([self::PROJECT_ROOT . '/src/api'], 'Test')->endpoints;

        $phoneEndpoints = array_values(array_filter(
            $endpoints,
            static fn (Endpoint $e) => str_contains($e->sourceFile ?? '', 'PhoneController.php'),
        ));

        self::assertCount(1, $phoneEndpoints, 'Expected exactly one endpoint, not a documented + urlManager duplicate');

        $phone = $phoneEndpoints[0];
        // The apidoc-documented (singular, correct) path must win over the
        // urlManager-guessed pluralized one ("/v1/phones/validate").
        self::assertSame('POST', $phone->method);
        self::assertSame('/v1/phone/validate', $phone->path);
        self::assertSame('Phone', $phone->group);
        self::assertNotEmpty($phone->params, 'Apidoc params (e.g. "phone") must survive, unlike the urlManager duplicate');
    }
}
