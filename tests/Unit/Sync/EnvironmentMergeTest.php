<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Sync;

use Jeytekdev\ApiSync\Export\PostmanExporter;
use Jeytekdev\ApiSync\Ir\Collection;
use Jeytekdev\ApiSync\Sync\CollectionMerger;
use PHPUnit\Framework\TestCase;

final class EnvironmentMergeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/api-sync-env-test-' . uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->dir);
    }

    public function testEnvironmentFileIsWrittenOnFirstGenerate(): void
    {
        $collection = new Collection('Demo API', [], baseUrl: 'https://api.example.test');
        $generated = (new PostmanExporter())->export($collection);

        $written = (new CollectionMerger())->merge('postman', $generated, $this->dir);

        self::assertArrayHasKey(PostmanExporter::ENVIRONMENT_FILE, $written);
    }

    public function testEnvironmentFileIsNeverOverwrittenOnceItExists(): void
    {
        $existingContent = '{"name":"Local","values":[{"key":"authToken","value":"user-filled-secret"}]}';
        file_put_contents($this->dir . '/' . PostmanExporter::ENVIRONMENT_FILE, $existingContent);

        $collection = new Collection('Demo API', [], baseUrl: 'https://api.example.test');
        $generated = (new PostmanExporter())->export($collection);

        $written = (new CollectionMerger())->merge('postman', $generated, $this->dir);

        self::assertArrayNotHasKey(PostmanExporter::ENVIRONMENT_FILE, $written);
    }
}
