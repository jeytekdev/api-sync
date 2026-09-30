<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Export;

use Jeytekdev\ApiSync\Export\InsomniaExporter;
use Jeytekdev\ApiSync\Ir\Collection;
use Jeytekdev\ApiSync\Ir\Endpoint;
use PHPUnit\Framework\TestCase;

final class InsomniaExporterTest extends TestCase
{
    public function testExportsAnEnvironmentResourceWithBaseUrlAndEmptyAuthToken(): void
    {
        $collection = new Collection('Demo API', [
            new Endpoint('GET', '/orders', group: 'orders'),
        ], baseUrl: 'https://api.example.test');

        $files = (new InsomniaExporter())->export($collection, 'Local');
        self::assertArrayHasKey('collection.insomnia.json', $files);

        $doc = json_decode($files['collection.insomnia.json'], true, flags: JSON_THROW_ON_ERROR);
        $environment = current(array_filter($doc['resources'], static fn ($r) => $r['_type'] === 'environment'));

        self::assertNotFalse($environment);
        self::assertSame('Local', $environment['name']);
        self::assertSame('https://api.example.test', $environment['data']['baseUrl']);
        self::assertSame('', $environment['data']['authToken']);
    }
}
