<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Unit\Support;

use Jeytekdev\ApiSync\Support\AstFile;
use Jeytekdev\ApiSync\Support\PhpLiteralEvaluator;
use PhpParser\Node\Expr\Array_;
use PHPUnit\Framework\TestCase;

final class PhpLiteralEvaluatorTest extends TestCase
{
    public function testANonStaticallyResolvableKeyIsAppendedRatherThanCoercedToNull(): void
    {
        $file = AstFile::parse(__DIR__ . '/../../Fixtures/Support/array-with-non-literal-key.php');
        self::assertNotNull($file);

        $expr = $file->topLevelReturnExpr();
        self::assertInstanceOf(Array_::class, $expr);

        $data = PhpLiteralEvaluator::evaluateArray($expr);

        self::assertSame('value', $data['plain']);
        self::assertSame(['inner' => 'ok'], $data['nested']);
        // The PHP_EOL-keyed item couldn't be resolved statically - it must
        // have been appended under an integer key, never under null/"".
        self::assertContains('skipped, key is not statically resolvable', $data);
        self::assertArrayNotHasKey('', $data);
    }
}
