<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Export;

use Jeytekdev\ApiSync\Export\BrunoExporter;
use Jeytekdev\ApiSync\Ir\Collection;
use Jeytekdev\ApiSync\Ir\Endpoint;
use PHPUnit\Framework\TestCase;

final class BrunoExporterTest extends TestCase
{
    public function testExportsOneFilePerRequestWithManagedMarker(): void
    {
        $collection = new Collection('Demo API', [
            new Endpoint('GET', '/orders', group: 'orders', name: 'List orders'),
        ]);

        $files = (new BrunoExporter())->export($collection);

        self::assertArrayHasKey('bruno.json', $files);
        self::assertArrayHasKey('orders/list-orders.bru', $files);
        self::assertStringStartsWith(BrunoExporter::MANAGED_MARKER, $files['orders/list-orders.bru']);
        self::assertStringContainsString('get {', $files['orders/list-orders.bru']);
        self::assertStringContainsString('url: {{baseUrl}}/orders', $files['orders/list-orders.bru']);
    }

    public function testExportsAnEnvironmentFileWithoutTheManagedMarker(): void
    {
        $collection = new Collection('Demo API', [], baseUrl: 'https://api.example.test');

        $files = (new BrunoExporter())->export($collection);

        self::assertArrayHasKey(BrunoExporter::ENVIRONMENT_FILE, $files);
        $env = $files[BrunoExporter::ENVIRONMENT_FILE];

        self::assertStringContainsString('baseUrl: https://api.example.test', $env);
        self::assertStringContainsString('authToken: ', $env);
        // No managed marker: CollectionMerger must never overwrite a filled-in environment file.
        self::assertStringNotContainsString(BrunoExporter::MANAGED_MARKER, $env);
    }
}
