<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Support;

use Jeytekdev\ApiSync\Support\PsrAutoloadResolver;
use Jeytekdev\ApiSync\Support\Yii2AuthResolver;
use PHPUnit\Framework\TestCase;

final class Yii2AuthResolverTest extends TestCase
{
    private const PROJECT_ROOT = __DIR__ . '/../../Yii2ProjectFixture';

    private function resolver(): Yii2AuthResolver
    {
        return new Yii2AuthResolver(PsrAutoloadResolver::forProjectRoot(self::PROJECT_ROOT));
    }

    public function testInfersAuthFromAnInheritedBehaviorsMethodUsingLegacyClassNameCall(): void
    {
        // PhoneController declares no behaviors() of its own; BaseController's
        // does, using Yii2's legacy `::className()` helper (not `::class`).
        self::assertSame('basic', $this->resolver()->authFor('app\api\v1\controllers\PhoneController'));
    }

    public function testWalksTheHierarchyForEveryConcreteController(): void
    {
        self::assertSame('basic', $this->resolver()->authFor('app\api\v1\controllers\MonitorController'));
    }

    public function testReturnsNullWhenTheClassCannotBeResolved(): void
    {
        self::assertNull($this->resolver()->authFor('yii\console\Controller'));
    }

    public function testReturnsNullWithoutAResolver(): void
    {
        $resolver = new Yii2AuthResolver(null);

        self::assertNull($resolver->authFor('app\api\v1\controllers\PhoneController'));
    }
}
