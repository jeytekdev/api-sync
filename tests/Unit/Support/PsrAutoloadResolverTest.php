<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Support;

use Jeytekdev\ApiSync\Support\PsrAutoloadResolver;
use PHPUnit\Framework\TestCase;

final class PsrAutoloadResolverTest extends TestCase
{
    private const PROJECT_ROOT = __DIR__ . '/../../Yii2ProjectFixture';

    public function testResolvesAnFqcnToItsFileViaPsr4(): void
    {
        $resolver = PsrAutoloadResolver::forProjectRoot(self::PROJECT_ROOT);

        $path = $resolver->resolve('app\api\v1\controllers\PhoneController');

        self::assertNotNull($path);
        self::assertSame(
            realpath(self::PROJECT_ROOT . '/src/api/v1/controllers/PhoneController.php'),
            realpath($path),
        );
    }

    public function testReturnsNullForAnUnmappedNamespace(): void
    {
        $resolver = PsrAutoloadResolver::forProjectRoot(self::PROJECT_ROOT);

        self::assertNull($resolver->resolve('yii\console\Controller'));
    }

    public function testReturnsNullWhenTheProjectHasNoComposerJson(): void
    {
        $resolver = PsrAutoloadResolver::forProjectRoot(sys_get_temp_dir() . '/api-sync-no-such-project-' . uniqid());

        self::assertNull($resolver->resolve('app\api\v1\controllers\PhoneController'));
    }
}
