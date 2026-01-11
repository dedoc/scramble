<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Flow\TerminateNode;
use Dedoc\Scramble\Infer\Flow\TerminationType;
use Dedoc\Scramble\Infer\Flow\UnknownNode;
use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

class FlowBuilderHandler
{
    public function shouldHandle($node)
    {
        return $node instanceof Node\Stmt
            && ! $node instanceof Node\Stmt\Function_;
    }

    public function enter(Node\Stmt $node, Scope $scope)
    {
        if (! $scope->isInFunction()) {
            return;
        }

        $flow = $scope->getFlowNodes();

        if ($node instanceof Node\Stmt\Return_) {
            if ($node->expr instanceof Node\Expr\Match_) {
                $flow->pushTerminateMatch($node->expr);

                return;
            }

            $flow->pushTerminate(new TerminateNode(TerminationType::RETURN, $node->expr));

            return;
        }

        if ($node instanceof Node\Stmt\If_) {
            $flow->pushCondition(condition: $node->cond); // pushes node, makes "yes" branch head

            return;
        }

        if ($node instanceof Node\Stmt\ElseIf_) {
            $flow->pushConditionBranch(condition: $node->cond); // goes back to condition node, pushes the new branch

            return;
        }

        if ($node instanceof Node\Stmt\Else_) {
            $flow->pushConditionBranch(); // goes back to condition node, pushes the new branch

            return;
        }

        $flow->push(new UnknownNode($node)); // pushes node, make the node head
    }

    public function leave(Node\Stmt $node, Scope $scope)
    {
        if (! $scope->isInFunction()) {
            return;
        }

        $flow = $scope->getFlowNodes();

        if ($node instanceof Node\Stmt\If_) {
            $flow->exitCondition(); // pushes node, makes "yes" branch head

            return;
        }
    }
}
