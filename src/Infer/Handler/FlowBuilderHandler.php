<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Flow\Nodes;
use Dedoc\Scramble\Infer\Flow\TerminateNode;
use Dedoc\Scramble\Infer\Flow\TerminationKind;
use Dedoc\Scramble\Infer\Flow\StatementNode;
use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Expr;

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

        if(
            $node instanceof Node\Stmt\Expression
            && $node->expr instanceof Node\Expr\Assign
            && $node->expr->var instanceof Node\Expr\Variable
        ) {
            if ($node->expr->expr instanceof Node\Expr\Match_) {
                $this->pushAssignMatch($flow, $node->expr->var, $node->expr->expr);

                return;
            }
        }

        if ($node instanceof Node\Stmt\Return_) {
            if ($node->expr instanceof Node\Expr\Match_) {
                $this->pushTerminateMatch($flow, $node->expr);

                return;
            }

            $flow->pushTerminate(new TerminateNode(TerminationKind::RETURN, $node->expr));

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

        $flow->push(new StatementNode($node)); // pushes node, make the node head
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

    private function pushTerminateMatch(Nodes $flow, Node\Expr\Match_ $match): void
    {
        $flow->pushCondition();

        foreach ($match->arms as $arm) {
            if ($arm->conds === null) { // default arm
                $flow->pushConditionBranch(); // negated / else

                $flow->pushTerminate(new TerminateNode(TerminationKind::RETURN, $arm->body));

                continue;
            }

            foreach ($arm->conds as $cond) {
                $flow->pushConditionBranch(new Expr\BinaryOp\Identical($match->cond, $cond));

                $flow->pushTerminate(new TerminateNode(TerminationKind::RETURN, $arm->body));
            }
        }

        $flow->exitCondition();
    }

    private function pushAssignMatch(Nodes $flow, Variable $variable, Match_ $match): void
    {
        $flow->pushCondition();

        foreach ($match->arms as $arm) {
            if ($arm->conds === null) { // default arm
                $flow->pushConditionBranch(); // negated / else

                $flow->push(new StatementNode(new Expression(new Expr\Assign($variable, $arm->body))));

                continue;
            }

            foreach ($arm->conds as $cond) {
                $flow->pushConditionBranch(new Expr\BinaryOp\Identical($match->cond, $cond));

                $flow->push(new StatementNode(new Expression(new Expr\Assign($variable, $arm->body))));
            }
        }

        $flow->exitCondition();
    }
}
