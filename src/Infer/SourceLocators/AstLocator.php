<?php

namespace Dedoc\Scramble\Infer\SourceLocators;

use Dedoc\Scramble\Infer\Contracts\AstLocator as AstLocatorContract;
use Dedoc\Scramble\Infer\Contracts\SourceLocator;
use Dedoc\Scramble\Infer\Symbol;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\Parser;

class AstLocator implements AstLocatorContract
{
    public function __construct(
        private Parser $parser,
        public readonly SourceLocator $sourceLocator,
    )
    {
    }

    public function getSource(Symbol $symbol): ?Node
    {
        $ast = $this->parser->parse($this->sourceLocator->getSource($symbol));

        if ($symbol->kind === Symbol::KIND_FUNCTION) {
            return (new NodeFinder)->findFirst(
                $ast,
                fn ($n) => $n instanceof Function_ && (($n->name->name ?? null) === $symbol->name),
            );
        }

        if ($symbol->kind === Symbol::KIND_CLASS_METHOD) {
            $classNode = (new NodeFinder)->findFirst(
                $ast,
                fn ($n) => $n instanceof ClassLike && (($n->name->name ?? null) === $symbol->className),
            );

            return (new NodeFinder)->findFirst(
                $classNode,
                fn ($n) => $n instanceof Node\Stmt\ClassMethod && (($n->name->name ?? null) === $symbol->name),
            );
        }

        if ($symbol->kind === Symbol::KIND_CLASS) {
            return (new NodeFinder)->findFirst(
                $ast,
                fn ($n) => $n instanceof ClassLike && (($n->name->name ?? null) === $symbol->name),
            );
        }

        return null;
    }
}
