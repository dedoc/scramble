<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use WeakMap;

class FlowBuildingVisitor extends NodeVisitorAbstract
{
    public $symbolsFlowNodes = [];

    private ?FlowNodes $currentFlowNodes = null;

    private array $flowNodesStack = [];

    public function __construct(
        private NodeTraverser $traverser,
        private WeakMap $handledExpressions = new WeakMap(),
        private WeakMap $functionLikeFlowContainers = new WeakMap(),
        private WeakMap $skipNodes = new WeakMap(),
    )
    {
    }

    public function enterNode(Node $node)
    {
        if ($this->skipNodes->offsetExists($node)) {
            return NodeVisitor::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
        }

        if ($node instanceof Node\FunctionLike) {
            $this->currentFlowNodes = new FlowNodes([
                new EnterFunctionLikeFlowNode(
                    parameters: $node->getParams(),
                    node: $node,
                    containerAntecedent: $this->currentFlowNodes?->lastFlowable(),
                    antecedents: [],
                ),
            ]);
            $this->flowNodesStack[] = $this->currentFlowNodes;

            $this->functionLikeFlowContainers->offsetSet($node, $this->currentFlowNodes);

            $node->setAttribute('flowContainer', $this->currentFlowNodes);

            return null;
        }

        if ($node instanceof Node\Stmt\If_) {
            $this->pushConditionFlowNodes($node->cond);

            $this->currentFlowNodes = new FlowNodes(
                head: $this->currentFlowNodes->lastFlowable(),
            );

            $this->currentFlowNodes->push($node, fn (array $antecedents) => new ConditionFlowNode(
                truthyConditions: [$node->cond],
                falsyConditions: [],
                statement: $node,
                isTrue: true,
                antecedents: $antecedents,
            ));
            $this->flowNodesStack[] = $this->currentFlowNodes;

            return null;
        }

        if ($node instanceof Node\Stmt\ElseIf_) {
            $this->pushConditionFlowNodes($node->cond);

            $this->currentFlowNodes->push($node, fn (array $antecedents) => new ConditionFlowNode(
                truthyConditions: [$node->cond],
                falsyConditions: $this->currentFlowNodes->nodes[0]->truthyConditions,
                statement: $node,
                isTrue: true,
                antecedents: array_values(array_filter([$this->currentFlowNodes->head])),
            ));

            return null;
        }

        if ($node instanceof Node\Stmt\Else_) {
            $this->currentFlowNodes->push($node, fn (array $antecedents) => new ConditionFlowNode(
                truthyConditions: [],
                falsyConditions: $this->currentFlowNodes->nodes[0]->truthyConditions,
                statement: $node,
                isTrue: false,
                antecedents: array_values(array_filter([$this->currentFlowNodes->head])),
            ));

            return null;
        }
    }

    protected function pushConditionFlowNodes(Node\Expr $expression)
    {
        $this->traverser->traverse([$expression]);

        $this->skipNodes->offsetSet($expression, true);
    }

    public function leaveNode(Node $node)
    {
        if ($this->skipNodes->offsetExists($node)) {
            return null;
        }

        if (! $this->currentFlowNodes) {
            return null;
        }

        if ($node instanceof Node\FunctionLike) {
            if ($node instanceof Node\Expr\ArrowFunction) {
                $this->currentFlowNodes->push($node, fn (array $antecedent) => new TerminateFlowNode(
                    expression: $node->expr,
                    kind: TerminateFlowNode::KIND_RETURN,
                    antecedents: $antecedent,
                ));
            }

            if (isset($node->name)) {
                $this->symbolsFlowNodes[$node->name->toString()] = $this->currentFlowNodes;
            }

            array_pop($this->flowNodesStack);
            $this->currentFlowNodes = $this->flowNodesStack[count($this->flowNodesStack) - 1] ?? null;

            return null;
        }

        if (
            $node instanceof Node\Expr\Assign
            || $node instanceof Node\Expr\AssignOp
        ) {
            $this->currentFlowNodes->push($node, fn (array $antecedent) => new AssignmentFlowNode(
                expression: $node,
                kind: $node instanceof Node\Expr\Assign
                    ? AssignmentFlowNode::KIND_ASSIGN
                    : AssignmentFlowNode::KIND_ASSIGN_COMPOUND,
                antecedents: $antecedent,
            ));

            $this->handledExpressions->offsetSet($node, true);

            return null;
        }

        // call handle

        if (! $node instanceof Node\Stmt) {
            return null;
        }

        if ($node instanceof Node\Stmt\Return_) {
            $fn = $this->currentFlowNodes->push($node, fn (array $antecedent) => new TerminateFlowNode(
                expression: $node->expr,
                kind: TerminateFlowNode::KIND_RETURN,
                antecedents: $antecedent,
            ));

            return null;
        }

        if ($node instanceof Node\Stmt\If_) {
            /** @var FlowNodes $poppingFlow */
            $poppingFlow = array_pop($this->flowNodesStack);

            $this->currentFlowNodes = $this->flowNodesStack[count($this->flowNodesStack) - 1];

            $this->currentFlowNodes->mergeNodes($poppingFlow);

            $leafNodes = $poppingFlow->getConditionLeafNodes();
            if (count($leafNodes) <= 1) {
                if ($poppingFlow->head) {
                    $leafNodes[] = $poppingFlow->head;
                }
            }
            $leafNodes = array_values(array_filter(
                $leafNodes,
                fn ($f) => ! $f instanceof TerminateFlowNode,
            ));

            $conditionNodes = $poppingFlow->getConditionNodes();
            $containsIfElse = (
                collect($conditionNodes)->contains(fn (ConditionFlowNode $f) => $f->isTrue)
                && collect($conditionNodes)->contains(fn (ConditionFlowNode $f) => $f->isTrue === false)
            );
            // Make sure to preserve the control flow if there is no if/else pair
            if ($poppingFlow->head && !$containsIfElse) {
                if (! in_array($poppingFlow->head, $leafNodes)) {
                    $leafNodes[] = $poppingFlow->head;
                }
            }

            // BranchLabel!
            $this->currentFlowNodes->push(null, fn () => new BranchLabel($leafNodes));

            return null;
        }

        if ($node instanceof Node\Stmt\ElseIf_) {
            return null;
        }

        if ($node instanceof Node\Stmt\Else_) {
            return null;
        }

        if (
            $node instanceof Node\Stmt\Expression
            && $this->handledExpressions->offsetExists($node->expr)
        ) {
            return null;
        }

        $this->currentFlowNodes->push($node, fn (array $antecedent) => new BasicFlowNode(
            statement: $node,
            antecedents: $antecedent,
        ));

        return null;
    }
}
