<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Flow\TerminateNode;
use Dedoc\Scramble\Infer\Flow\TerminationType;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\VoidType;
use PhpParser\Node;

class ReturnHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Stmt\Return_;
    }

    public function leave(Node\Stmt\Return_ $node, Scope $scope)
    {
        if (! $scope->isInFunction()) {
            return;
        }

        $fnDefinition = $scope->functionDefinition();

        $fnDefinition->type->setReturnType(
            TypeHelper::mergeTypes(
                $node->expr ? $scope->getType($node->expr) : new VoidType,
                $fnDefinition->type->getReturnType(),
            )
        );

        $this->attachTerminationNode($node, $scope);
    }

    private function attachTerminationNode(Node\Stmt\Return_ $node, Scope $scope): void
    {
        if ($node->expr instanceof Node\Expr\Match_) {
            $this->attachMatchTerminationNode($node->expr, $scope);

            return;
        }

        if ($node->expr instanceof Node\Expr\Ternary) {
            return;
        }

        $scope->getFlowNodes()->push(new TerminateNode(
            type: TerminationType::RETURN,
            value: $node->expr,
        ));
    }

    private function attachMatchTerminationNode(Node\Expr\Match_ $expr, Scope $scope): void
    {
        $matchHeadNode = new ConditionNode(

        );

        $scope->getFlowNodes()->push($matchHeadNode);
    }
}
