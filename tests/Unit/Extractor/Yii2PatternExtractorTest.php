<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Extractor;

use Jeytekdev\ApiSync\Extractor\RouteSource;
use Jeytekdev\ApiSync\Extractor\Yii2Config;
use Jeytekdev\ApiSync\Extractor\Yii2PatternExtractor;
use Jeytekdev\ApiSync\Support\AstFile;
use Jeytekdev\ApiSync\Support\PsrAutoloadResolver;
use PHPUnit\Framework\TestCase;

final class Yii2PatternExtractorTest extends TestCase
{
    private const YII2_PROJECT_ROOT = __DIR__ . '/../../Yii2ProjectFixture';

    public function testExtractsRestConventionRoutes(): void
    {
        $file = AstFile::parse(__DIR__ . '/../../Fixtures/Yii2/UserController.php');
        self::assertNotNull($file);

        $candidates = (new Yii2PatternExtractor())->extract($file);
        $routes = array_map(static fn ($c) => $c->httpMethod . ' ' . $c->path, $candidates);

        self::assertContains('GET /user', $routes);
        self::assertContains('GET /user/{id}', $routes);
        self::assertContains('POST /user', $routes);
        self::assertContains('DELETE /user/{id}', $routes);
        self::assertCount(4, $candidates);
    }

    public function testConsoleControllersProduceNoCandidates(): void
    {
        $file = AstFile::parse(self::YII2_PROJECT_ROOT . '/src/console/CronController.php');
        self::assertNotNull($file);

        self::assertSame([], (new Yii2PatternExtractor())->extract($file));
    }

    public function testUrlManagerRouteWinsOverGuessAndPicksUpTheModulePrefix(): void
    {
        $resolver = PsrAutoloadResolver::forProjectRoot(self::YII2_PROJECT_ROOT);
        $yii2Config = Yii2Config::discover(self::YII2_PROJECT_ROOT, $resolver);

        $file = AstFile::parse(self::YII2_PROJECT_ROOT . '/src/api/v1/controllers/PhoneController.php');
        self::assertNotNull($file);

        $candidates = (new Yii2PatternExtractor($yii2Config))->extract($file);
        self::assertCount(1, $candidates);

        $candidate = $candidates[0];
        self::assertSame('POST', $candidate->httpMethod);
        self::assertSame('/v1/phones/validate', $candidate->path);
        self::assertSame(RouteSource::UrlManager, $candidate->source);
        self::assertSame('app\api\v1\controllers\PhoneController', $candidate->controllerFqcn);
    }

    public function testFallsBackToNamingGuessWithModulePrefixWhenNoUrlManagerRuleMatches(): void
    {
        $resolver = PsrAutoloadResolver::forProjectRoot(self::YII2_PROJECT_ROOT);
        $yii2Config = Yii2Config::discover(self::YII2_PROJECT_ROOT, $resolver);

        $file = AstFile::parse(self::YII2_PROJECT_ROOT . '/src/api/v1/controllers/MonitorController.php');
        self::assertNotNull($file);

        $candidates = (new Yii2PatternExtractor($yii2Config))->extract($file);
        self::assertCount(1, $candidates);

        $candidate = $candidates[0];
        self::assertSame('GET', $candidate->httpMethod);
        self::assertSame('/v1/monitor', $candidate->path);
        self::assertSame(RouteSource::Convention, $candidate->source);
    }
}
