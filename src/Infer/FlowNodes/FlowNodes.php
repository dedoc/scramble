<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use WeakMap;

class FlowNodes
{
    public function __construct(
        public array $nodes = [],
        public ?FlowNode $head = null,

        public WeakMap $nodesFlowNodes = new WeakMap(),

        private ?FlowNode $currentConditionFlow = null,
        private ?WeakMap $conditionFlowStacksLeafs = new WeakMap(),
    )
    {
    }

    public function push($node, callable $flowNodeGetter)
    {
        $lastNodes = array_values(array_filter(
            [$this->nodes[count($this->nodes) - 1] ?? $this->head],
            fn($flowNode) => $flowNode && !$flowNode instanceof TerminateFlowNode,
        ));
        $this->nodes[] = $flowNode = $flowNodeGetter($lastNodes);
        if ($node) {
            $this->nodesFlowNodes->offsetSet($node, $flowNode);
        }

        // rewire antecedents in case of else branches
        if ($flowNode instanceof ConditionFlowNode) {
            $this->currentConditionFlow = $flowNode;
            $this->conditionFlowStacksLeafs->offsetSet($flowNode, $flowNode);
        }

        if ($this->currentConditionFlow) {
            $this->conditionFlowStacksLeafs->offsetSet($this->currentConditionFlow, $flowNode);
        }

        return $flowNode;
    }

    public function lastFlowable(): ?FlowNode
    {
        $last = $this->nodes[count($this->nodes) - 1] ?? $this->head;

        if (! $last) {
            return null;
        }

        return $last instanceof TerminateFlowNode ? null : $last;
    }

    public function mergeNodes(FlowNodes $flowNodes)
    {
        $this->nodes = array_merge($this->nodes, $flowNodes->nodes);

        return $this;
    }

    public function getConditionLeafNodes(): array
    {
        $conditions = [];
        $newAntecedents = [];
        foreach ($this->conditionFlowStacksLeafs as $condition => $newAntecedent) {
            $conditions[] = $condition;
            $newAntecedents[] = $newAntecedent;
        }
        return $newAntecedents;
    }

    public function getConditionNodes(): array
    {
        $conditions = [];
        $newAntecedents = [];
        foreach ($this->conditionFlowStacksLeafs as $condition => $newAntecedent) {
            $conditions[] = $condition;
            $newAntecedents[] = $newAntecedent;
        }
        return $conditions;
    }

    public function getCurrentIfFlow(): ?ConditionFlowNode
    {
        return $this->ifFlowStack[array_key_first($this->ifFlowStack)] ?? null;
    }

    public function startIfCondition($node, callable $flowNodeGetter)
    {
        $ifFlow = $this->push($node, $flowNodeGetter);
        $this->ifFlowStack = [$ifFlow, ...$this->ifFlowStack];
        return $ifFlow;
    }

    public function endIfCondition()
    {
        $conditions = [];
        $newAntecedents = [];
        foreach ($this->conditionFlowStacksLeafs as $condition => $newAntecedent) {
            $conditions[] = $condition;
            $newAntecedents[] = $newAntecedent;
        }

        if (count($conditions) === 1) {
            $newAntecedents = array_merge($newAntecedents, $conditions[0]->antecedents);
        }

        array_shift($this->ifFlowStack);
        $this->currentConditionFlow = null;
        $this->conditionFlowStacksLeafs = new WeakMap;

        // @todo check if terminate in new antecedents and avoid branch label if there is one non-terminate antecedent

        $this->push(null, fn() => new BranchLabel($newAntecedents));
    }
}
