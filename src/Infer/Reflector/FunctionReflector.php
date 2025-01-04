<?php

namespace Dedoc\Scramble\Infer\Reflector;

use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\FlowNodes\FlowBuildingVisitor;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeGetter;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeResolver;
use Dedoc\Scramble\Support\Type\FunctionType;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class FunctionReflector
{
    public function __construct(
        private readonly Index $index,
        public readonly string $name,
        private readonly string $code, // @todo
    )
    {
    }

    public static function makeFromCodeString(string $name, string $code, Index $index): static
    {
//        $sourceLocator = new CodeStringSourceLocator($code);

        return new static(
            $index,
            $name,
//            $sourceLocator->getFunctionAst($name),
            $code,
        );
    }

    /**
     * @internal
     */
    public function getIncompleteType(): FunctionType
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse($this->code);
        $functionNode = (new NodeFinder)->findFirst(
            $ast,
            fn ($n) => $n instanceof Function_ && (($n->name->name ?? null) === $this->name),
        );
        if (! $functionNode) {
            throw new \LogicException("Cannot locate [$this->name] function node in AST");
        }

        /** @var Function_ $functionNode */

        $traverser = new NodeTraverser(
            // new PhpParser\NodeVisitor\ParentConnectingVisitor(),
            // new \PhpParser\NodeVisitor\NameResolver(),
        );
        $traverser->addVisitor($flowVisitor = new FlowBuildingVisitor($traverser));

        $traverser->traverse([$functionNode]);

        $incompleteFunctionType = (new IncompleteTypeGetter())->getFunctionType($functionNode, $this->name);

        if (! $incompleteFunctionType) {
            throw new \LogicException("Cannot locate flow node container in [$this->name] function AST node");
        }

        return $incompleteFunctionType;
    }

    public function getType(): FunctionType
    {
        $incompleteTypesResolver = new IncompleteTypeResolver($this->index);

        return $incompleteTypesResolver->resolve($this->getIncompleteType());
    }
}
