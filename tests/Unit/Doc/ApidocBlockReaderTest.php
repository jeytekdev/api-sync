<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Doc;

use Jeytekdev\ApiSync\Doc\ApidocBlockReader;
use Jeytekdev\ApiSync\Extractor\Yii2PatternExtractor;
use Jeytekdev\ApiSync\Support\AstFile;
use PHPUnit\Framework\TestCase;

final class ApidocBlockReaderTest extends TestCase
{
    public function testParsesApidocTags(): void
    {
        $file = AstFile::parse(__DIR__ . '/../../Fixtures/Yii2/UserController.php');
        self::assertNotNull($file);

        $candidates = (new Yii2PatternExtractor())->extract($file);
        $view = current(array_filter($candidates, static fn ($c) => $c->httpMethod === 'GET' && $c->path === '/user/{id}'));
        self::assertNotFalse($view);

        $fragment = (new ApidocBlockReader())->read($view);

        self::assertSame('Get a single user', $fragment->description);
        self::assertCount(1, $fragment->params);
        self::assertSame('id', $fragment->params[0]->name);
        self::assertTrue($fragment->params[0]->required);

        $statuses = array_map(static fn ($r) => $r->status, $fragment->responses);
        self::assertContains(200, $statuses);
        self::assertContains(404, $statuses);
    }

    public function testOptionalParamIsMarkedNotRequired(): void
    {
        $file = AstFile::parse(__DIR__ . '/../../Fixtures/Yii2/UserController.php');
        self::assertNotNull($file);

        $candidates = (new Yii2PatternExtractor())->extract($file);
        $index = current(array_filter($candidates, static fn ($c) => $c->httpMethod === 'GET' && $c->path === '/user'));
        self::assertNotFalse($index);

        $fragment = (new ApidocBlockReader())->read($index);

        self::assertSame('page', $fragment->params[0]->name);
        self::assertFalse($fragment->params[0]->required);
    }
}
