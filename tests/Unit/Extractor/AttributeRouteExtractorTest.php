<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Extractor;

use Jeytekdev\ApiSync\Doc\AttributeDocReader;
use Jeytekdev\ApiSync\Extractor\AttributeRouteExtractor;
use Jeytekdev\ApiSync\Support\AstFile;
use PHPUnit\Framework\TestCase;

final class AttributeRouteExtractorTest extends TestCase
{
    public function testExtractsRouteAndDocAttributes(): void
    {
        $file = AstFile::parse(__DIR__ . '/../../Fixtures/Attributes/ProductController.php');
        self::assertNotNull($file);

        $candidates = (new AttributeRouteExtractor())->extract($file);
        self::assertCount(1, $candidates);

        $candidate = $candidates[0];
        self::assertSame('GET', $candidate->httpMethod);
        self::assertSame('/products/{id}', $candidate->path);
        self::assertSame('products.show', $candidate->name);

        $fragment = (new AttributeDocReader())->read($candidate);
        self::assertSame('products', $fragment->group);
        self::assertSame('v1', $fragment->version);
        self::assertSame('bearer', $fragment->auth);
        self::assertSame('Get a single product', $fragment->description);
        self::assertCount(1, $fragment->params);
        self::assertSame('id', $fragment->params[0]->name);
        self::assertCount(2, $fragment->responses);
    }
}
