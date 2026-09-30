<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Extractor;

use Jeytekdev\ApiSync\Extractor\Yii2Config;
use PHPUnit\Framework\TestCase;

final class Yii2ConfigDiscoveryTest extends TestCase
{
    private const PROJECT_ROOT = __DIR__ . '/../../Yii2ConfigDiscoveryFixture';

    public function testFollowsAUrlManagerValueRequiredFromASeparateFile(): void
    {
        $config = Yii2Config::discover(self::PROJECT_ROOT);

        // app/config/main.php has 'urlManager' => require __DIR__ . '/url-manager.php',
        // which itself returns ['rules' => ['GET api/ping' => 'ping/index']].
        self::assertSame(['index' => ['method' => 'GET', 'path' => '/api/ping']], $config->rulesFor('ping'));
    }

    public function testPicksUpAStandaloneFileNamedLikeUrlRulesEvenWithoutBeingRequired(): void
    {
        $config = Yii2Config::discover(self::PROJECT_ROOT);

        // app2/config/url_rules.php is never require()'d by anything, but is
        // picked up directly because of its name.
        self::assertSame(['index' => ['method' => 'GET', 'path' => '/api/pong']], $config->rulesFor('pong'));
    }

    public function testDoesNotMisreadAnUnrelatedConfigFileAsRoutes(): void
    {
        $config = Yii2Config::discover(self::PROJECT_ROOT);

        // app2/config/db.php has a top-level 'class' key too, but isn't
        // named like a urlManager file and has no urlManager/components key.
        self::assertSame([], $config->rulesFor('db'));
        self::assertSame([], $config->rulesFor('yii/db'));
    }

    public function testReturnsAnEmptyConfigForANonExistentProjectRoot(): void
    {
        $config = Yii2Config::discover(self::PROJECT_ROOT . '/no-such-dir');

        self::assertSame([], $config->rulesFor('anything'));
    }
}
