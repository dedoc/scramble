<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\FunctionLikeType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\VoidType;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;

class FunctionLikeHandler implements CreatesScope
{
    public function createScope(Scope $scope): Scope
    {
        return new Scope($scope);
    }

    public function shouldHandle($node)
    {
        return $node instanceof FunctionLike;
    }

    public function enter(FunctionLike $node)
    {
        $type = new FunctionType();

        $node->setAttribute('type', $type);
    }

    public function leave(FunctionLike $node)
    {
        /** @var $type FunctionLikeType */
        if (! $type = $node->getAttribute('type')) {
            throw new \LogicException('Type should have been set on node, but was not.');
        }

        if ($returnTypeAnnotation = $node->getReturnType()) {
            $type->setReturnType(TypeHelper::createTypeFromTypeNode($returnTypeAnnotation) ?: new VoidType);

            return;
        }

        /** @var Node\Stmt\Return_[] $returnNodes */
        $returnNodes = (new NodeFinder)->find(
            $node->getStmts(),
            fn (Node $n) => $n instanceof Node\Stmt\Return_
                && $node->getAttribute('scope') === $n->getAttribute('scope')
        );

        $types = array_filter(array_map(
            fn (Node\Stmt\Return_ $n) => $n->expr ? $n->expr->getAttribute('type') : new VoidType,
            $returnNodes,
        ));

        $type->setReturnType(Union::wrap($types));
    }
}
