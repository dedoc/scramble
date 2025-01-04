<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinitionBuilder;
use Dedoc\Scramble\Infer\FlowNodes\FlowBuildingVisitor;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeGetter;
use Dedoc\Scramble\Infer\Reflection\ReflectionFunction;
use Dedoc\Scramble\Infer\Symbol;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class FunctionLikeAstDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    public function __construct(
        public readonly ReflectionFunction $reflectionFunction,
        public readonly Parser $parser,
    )
    {
    }

    public function build(): FunctionLikeDefinition
    {
        $source = $this->reflectionFunction->sourceLocator->getSource(Symbol::createForFunction($this->reflectionFunction->name));

        $ast = $this->parser->parse($source);
        $functionNode = (new NodeFinder)->findFirst(
            $ast,
            fn ($n) => $n instanceof Function_ && (($n->name->name ?? null) === $this->reflectionFunction->name),
        );
        if (! $functionNode) {
            throw new \LogicException("Cannot locate [{$this->reflectionFunction->name}] function node in AST");
        }

        /** @var Function_ $functionNode */

        $traverser = new NodeTraverser(
            // new PhpParser\NodeVisitor\ParentConnectingVisitor(),
            // new \PhpParser\NodeVisitor\NameResolver(),
        );
        $traverser->addVisitor($flowVisitor = new FlowBuildingVisitor($traverser));

        $traverser->traverse([$functionNode]);

        $incompleteFunctionType = (new IncompleteTypeGetter())->getFunctionType($functionNode, $this->reflectionFunction->name);

        if (! $incompleteFunctionType) {
            throw new \LogicException("Cannot locate flow node container in [{$this->reflectionFunction->name}] function AST node");
        }

        return new \Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition($incompleteFunctionType);
    }
}
