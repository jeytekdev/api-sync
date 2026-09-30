<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Extractor;

use Jeytekdev\ApiSync\Extractor\LaravelPatternExtractor;
use Jeytekdev\ApiSync\Support\AstFile;
use PHPUnit\Framework\TestCase;

final class LaravelPatternExtractorTest extends TestCase
{
    public function testExtractsRouteFacadeCalls(): void
    {
        $file = AstFile::parse(__DIR__ . '/../../Fixtures/Laravel/routes.php');
        self::assertNotNull($file);

        $candidates = (new LaravelPatternExtractor())->extract($file);
        $routes = array_map(static fn ($c) => $c->httpMethod . ' ' . $c->path, $candidates);

        self::assertSame(['GET /orders', 'POST /orders', 'GET /orders/{id}'], $routes);
        foreach ($candidates as $candidate) {
            self::assertSame('orders', $candidate->group);
        }
    }
}
