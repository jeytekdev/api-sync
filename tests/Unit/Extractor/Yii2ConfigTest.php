<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Extractor;

use Jeytekdev\ApiSync\Extractor\Yii2Config;
use Jeytekdev\ApiSync\Support\PsrAutoloadResolver;
use PHPUnit\Framework\TestCase;

final class Yii2ConfigTest extends TestCase
{
    private const PROJECT_ROOT = __DIR__ . '/../../Yii2ProjectFixture';

    private function config(): Yii2Config
    {
        $resolver = PsrAutoloadResolver::forProjectRoot(self::PROJECT_ROOT);

        return Yii2Config::discover(self::PROJECT_ROOT, $resolver);
    }

    public function testRestRuleWithDefaultPluralizeAndExtraPattern(): void
    {
        $rules = $this->config()->rulesFor('v1/phone');

        self::assertSame(['method' => 'GET', 'path' => '/v1/phones'], $rules['index']);
        self::assertSame(['method' => 'GET', 'path' => '/v1/phones/{id}'], $rules['view']);
        self::assertSame(['method' => 'POST', 'path' => '/v1/phones'], $rules['create']);
        self::assertSame(['method' => 'PUT', 'path' => '/v1/phones/{id}'], $rules['update']);
        self::assertSame(['method' => 'DELETE', 'path' => '/v1/phones/{id}'], $rules['delete']);
        self::assertSame(['method' => 'POST', 'path' => '/v1/phones/validate'], $rules['validate']);
    }

    public function testRestRuleWithPluralizeFalseAndAllDefaultsExcepted(): void
    {
        $rules = $this->config()->rulesFor('v1/hlr');

        self::assertSame(['send' => ['method' => 'POST', 'path' => '/v1/hlr']], $rules);
    }

    public function testRestRuleWithTokenPlaceholderInExtraPattern(): void
    {
        $rules = $this->config()->rulesFor('v1/firebase');

        self::assertSame(
            ['score-for-recaptcha' => ['method' => 'GET', 'path' => '/v1/firebase/score-for-recaptcha/{os}']],
            $rules,
        );
    }

    public function testPlainStringRule(): void
    {
        $rules = $this->config()->rulesFor('status');

        self::assertSame(['index' => ['method' => 'GET', 'path' => '/api/status']], $rules);
    }

    public function testUnknownControllerHasNoRules(): void
    {
        self::assertSame([], $this->config()->rulesFor('v1/unknown'));
    }

    public function testModulePrefixIsDetectedFromModuleControllerNamespaceProperty(): void
    {
        self::assertSame('v1', $this->config()->modulePrefixFor('app\api\v1\controllers'));
        self::assertNull($this->config()->modulePrefixFor('app\console'));
    }
}
