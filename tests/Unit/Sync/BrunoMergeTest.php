<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Sync;

use Jeytekdev\ApiSync\Export\BrunoExporter;
use Jeytekdev\ApiSync\Ir\Collection;
use Jeytekdev\ApiSync\Ir\Endpoint;
use Jeytekdev\ApiSync\Sync\CollectionMerger;
use PHPUnit\Framework\TestCase;

final class BrunoMergeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/api-sync-bruno-' . uniqid();
        mkdir($this->dir . '/orders', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/orders/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->dir . '/orders');
        if (is_file($this->dir . '/bruno.json')) {
            unlink($this->dir . '/bruno.json');
        }
        rmdir($this->dir);
    }

    public function testSkipsFileWithMarkerRemovedAndRegeneratesManagedFile(): void
    {
        file_put_contents(
            $this->dir . '/orders/list-orders.bru',
            "# hand-edited, no longer managed\nget { url: custom }\n"
        );

        $collection = new Collection('Demo API', [
            new Endpoint('GET', '/orders', group: 'orders', name: 'List orders'),
        ]);
        $generated = (new BrunoExporter())->export($collection);

        $written = (new CollectionMerger(prune: false))->merge('bruno', $generated, $this->dir);

        self::assertArrayNotHasKey('orders/list-orders.bru', $written);
        self::assertArrayHasKey('bruno.json', $written);
    }
}
