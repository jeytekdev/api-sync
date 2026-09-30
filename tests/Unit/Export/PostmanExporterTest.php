<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Export;

use Jeytekdev\ApiSync\Export\PostmanExporter;
use Jeytekdev\ApiSync\Ir\Collection;
use Jeytekdev\ApiSync\Ir\Endpoint;
use Jeytekdev\ApiSync\Ir\Param;
use PHPUnit\Framework\TestCase;

final class PostmanExporterTest extends TestCase
{
    public function testExportsFoldersPerGroupWithManagedMarkers(): void
    {
        $collection = new Collection('Demo API', [
            new Endpoint('GET', '/user/{id}', group: 'users', params: [
                new Param('id', Param::IN_PATH, 'int', true),
            ]),
        ], baseUrl: 'https://api.example.test');

        $files = (new PostmanExporter())->export($collection);
        self::assertArrayHasKey('collection.postman.json', $files);

        $doc = json_decode($files['collection.postman.json'], true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('Demo API', $doc['info']['name']);
        self::assertSame('users', $doc['item'][0]['name']);

        $item = $doc['item'][0]['item'][0];
        self::assertTrue($item['_apiSyncManaged']);
        self::assertSame('GET /user/{}', $item['_apiSyncId']);
        self::assertSame('GET', $item['request']['method']);
        self::assertSame('{{baseUrl}}/user/:id', $item['request']['url']['raw']);
        self::assertSame('id', $item['request']['url']['variable'][0]['key']);
    }

    public function testExportsAnEnvironmentFileWithBaseUrlAndEmptyAuthToken(): void
    {
        $collection = new Collection('Demo API', [], baseUrl: 'https://api.example.test');

        $files = (new PostmanExporter())->export($collection, 'Local');

        self::assertArrayHasKey(PostmanExporter::ENVIRONMENT_FILE, $files);
        $env = json_decode($files[PostmanExporter::ENVIRONMENT_FILE], true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('Local', $env['name']);
        self::assertSame('environment', $env['_postman_variable_scope']);

        $byKey = array_column($env['values'], null, 'key');
        self::assertSame('https://api.example.test', $byKey['baseUrl']['value']);
        self::assertSame('', $byKey['authToken']['value']);
        self::assertSame('secret', $byKey['authToken']['type']);
    }
}
