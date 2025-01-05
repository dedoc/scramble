<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\AstLocator;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinitionBuilder;
use Dedoc\Scramble\Infer\FlowNodes\FlowBuildingVisitor;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeGetter;
use Dedoc\Scramble\Infer\Symbol;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;

class FunctionLikeAstDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    public function __construct(
        public readonly string $name,
        public readonly AstLocator $astLocator,
    ) {}

    public function build(): FunctionLikeDefinition
    {
        /** @var Function_ $functionNode */
        $functionNode = $this->astLocator->getSource(Symbol::createForFunction($this->name));
        if (! $functionNode) {
            throw new \LogicException("Cannot locate [{$this->name}] function node in AST");
        }

        $traverser = new NodeTraverser;
        // new PhpParser\NodeVisitor\ParentConnectingVisitor(),
        // new \PhpParser\NodeVisitor\NameResolver(),
        $traverser->addVisitor($flowVisitor = new FlowBuildingVisitor($traverser));

        $traverser->traverse([$functionNode]);

        $incompleteFunctionType = (new IncompleteTypeGetter)->getFunctionType($functionNode, $this->name);

        if (! $incompleteFunctionType) {
            throw new \LogicException("Cannot locate flow node container in [{$this->name}] function AST node");
        }

        return new \Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition($incompleteFunctionType);
    }
}
