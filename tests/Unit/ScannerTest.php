<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit;

use Jeytekdev\ApiSync\Doc\ApidocBlockReader;
use Jeytekdev\ApiSync\Doc\AttributeDocReader;
use Jeytekdev\ApiSync\Ir\Endpoint;
use Jeytekdev\ApiSync\Pipeline;
use Jeytekdev\ApiSync\Scanner;
use PHPUnit\Framework\TestCase;

final class ScannerTest extends TestCase
{
    private function scan(): array
    {
        $scanner = new Scanner(Pipeline::extractors(), [new ApidocBlockReader(), new AttributeDocReader()]);

        return $scanner->scan([__DIR__ . '/../Fixtures'], 'Test')->endpoints;
    }

    public function testApidocRouteOverridesGuessedNamingConventionRoute(): void
    {
        $endpoints = $this->scan();

        $phone = current(array_filter(
            $endpoints,
            static fn (Endpoint $e) => str_contains($e->sourceFile ?? '', 'PhoneController.php'),
        ));

        self::assertNotFalse($phone);
        self::assertSame('POST', $phone->method);
        self::assertSame('/v1/phone/validate', $phone->path);
    }

    public function testApidocRouteNeverOverridesAnExplicitAttributeRoute(): void
    {
        $endpoints = $this->scan();

        // ProductController's #[Route] method also carries a conflicting
        // @api docblock tag on purpose (see the fixture) - it must survive
        // untouched as its own attribute-sourced endpoint, alongside (not
        // instead of) the apidoc-sourced one the block also produces.
        $product = current(array_filter(
            $endpoints,
            static fn (Endpoint $e) => str_contains($e->sourceFile ?? '', 'ProductController.php') && $e->method === 'GET',
        ));

        self::assertNotFalse($product);
        self::assertSame('/products/{id}', $product->path);
        self::assertSame('products.show', $product->name);

        $legacy = current(array_filter(
            $endpoints,
            static fn (Endpoint $e) => str_contains($e->sourceFile ?? '', 'ProductController.php') && $e->method === 'POST',
        ));

        self::assertNotFalse($legacy);
        self::assertSame('/legacy/product/{id}', $legacy->path);
    }
}
