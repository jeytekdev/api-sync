<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Sync;

use Jeytekdev\ApiSync\Export\PostmanExporter;
use Jeytekdev\ApiSync\Ir\Collection;
use Jeytekdev\ApiSync\Ir\Endpoint;
use Jeytekdev\ApiSync\Sync\CollectionMerger;
use PHPUnit\Framework\TestCase;

final class CollectionMergerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/api-sync-test-' . uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $file = $this->dir . '/collection.postman.json';
        if (is_file($file)) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testPreservesManualEditsAddsNewAndDeprecatesRemoved(): void
    {
        $existing = [
            'info' => ['name' => 'Demo API'],
            'item' => [
                [
                    'name' => 'users',
                    'item' => [
                        [
                            '_apiSyncId' => 'GET /user/{}',
                            '_apiSyncManaged' => true,
                            'name' => 'old name',
                            'request' => ['method' => 'GET', 'url' => ['raw' => 'old']],
                            'event' => [['listen' => 'test', 'script' => ['exec' => ['pm.test("ok", () => {});']]]],
                            'response' => [['name' => 'saved example']],
                        ],
                        [
                            '_apiSyncId' => 'GET /user/legacy',
                            '_apiSyncManaged' => true,
                            'name' => 'legacy',
                            'request' => ['method' => 'GET', 'url' => ['raw' => 'legacy']],
                        ],
                        [
                            'name' => 'manually added request',
                            'request' => ['method' => 'GET', 'url' => ['raw' => 'manual']],
                        ],
                    ],
                ],
            ],
        ];
        file_put_contents($this->dir . '/collection.postman.json', json_encode($existing));

        $collection = new Collection('Demo API', [
            new Endpoint('GET', '/user/{id}', group: 'users'),
        ]);
        $generated = (new PostmanExporter())->export($collection);

        $merged = (new CollectionMerger(prune: false))->merge('postman', $generated, $this->dir);
        $doc = json_decode($merged['collection.postman.json'], true, flags: JSON_THROW_ON_ERROR);

        $usersFolder = current(array_filter($doc['item'], static fn ($f) => $f['name'] === 'users'));
        self::assertNotFalse($usersFolder);

        $items = $usersFolder['item'];
        self::assertCount(2, $items); // regenerated endpoint + manually added request

        $regenerated = current(array_filter($items, static fn ($i) => ($i['_apiSyncId'] ?? null) === 'GET /user/{}'));
        self::assertNotFalse($regenerated);
        self::assertSame('GET /user/{id}', $regenerated['name']);
        self::assertSame([['listen' => 'test', 'script' => ['exec' => ['pm.test("ok", () => {});']]]], $regenerated['event']);
        self::assertSame([['name' => 'saved example']], $regenerated['response']);

        $manual = current(array_filter($items, static fn ($i) => $i['name'] === 'manually added request'));
        self::assertNotFalse($manual);

        $deprecatedFolder = current(array_filter($doc['item'], static fn ($f) => $f['name'] === '_deprecated'));
        self::assertNotFalse($deprecatedFolder);
        self::assertSame('GET /user/legacy', $deprecatedFolder['item'][0]['_apiSyncId']);
        self::assertTrue($deprecatedFolder['item'][0]['_apiSyncDeprecated']);
    }
}
