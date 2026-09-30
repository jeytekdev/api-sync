<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Support;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Parses a single PHP source file into an AST, purely statically:
 * no autoloading, no execution, no dependency on the target project's
 * installed packages or framework runtime.
 */
final class AstFile
{
    private static ?Parser $parser = null;

    /** @var Node[] */
    private readonly array $ast;

    private readonly NodeFinder $finder;

    /** @var string[]|null lazily computed by docCommentBlocks() */
    private ?array $docCommentBlocks = null;

    private function __construct(public readonly string $path, array $ast, private readonly string $code)
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $this->ast = $traverser->traverse($ast);
        $this->finder = new NodeFinder();
    }

    public static function parse(string $path): ?self
    {
        $code = @file_get_contents($path);
        if ($code === false) {
            return null;
        }

        try {
            $ast = self::parser()->parse($code) ?? [];
        } catch (Error) {
            return null;
        }

        return new self($path, $ast, $code);
    }

    /**
     * All `/** ... *\/` doc-comment blocks in the file, in source order,
     * regardless of whether the AST attaches them to any node - apidoc.js
     * blocks are routinely written as a free-standing header well before
     * the class/methods they document, so per-node comment lookup misses
     * them entirely.
     *
     * @return string[]
     */
    public function docCommentBlocks(): array
    {
        if ($this->docCommentBlocks !== null) {
            return $this->docCommentBlocks;
        }

        $blocks = [];
        foreach (token_get_all($this->code) as $token) {
            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                $blocks[] = $token[1];
            }
        }

        return $this->docCommentBlocks = $blocks;
    }

    /**
     * The expression of the last top-level `return ...;` statement (a
     * single optional wrapping `namespace {...}` block is unwrapped) -
     * used to read plain config files (`return [...]`) without picking up
     * `return` statements from nested closures/functions, which a full
     * recursive AST search would also match.
     */
    public function topLevelReturnExpr(): ?Expr
    {
        $stmts = $this->ast;
        if (count($stmts) === 1 && $stmts[0] instanceof Stmt\Namespace_) {
            $stmts = $stmts[0]->stmts;
        }

        $expr = null;
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Stmt\Return_ && $stmt->expr !== null) {
                $expr = $stmt->expr;
            }
        }

        return $expr;
    }

    private static function parser(): Parser
    {
        return self::$parser ??= (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @template T of Node
     * @param class-string<T> $class
     * @return T[]
     */
    public function find(string $class): array
    {
        return $this->finder->findInstanceOf($this->ast, $class);
    }
}
