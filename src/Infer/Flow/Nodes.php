<?php

namespace Dedoc\Scramble\Infer\Flow;

use Closure;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\Variable;
use PhpParser\PrettyPrinter;

class Nodes
{
    /** @var Node[] */
    public array $nodes = [];

    public array $edges = [];

    public ?Node $head;

    private array $conditionNodesStack = [];

    public ?Edge $conditionEdge = null;

    public function __construct()
    {
        $this->head = new StartNode;
        $this->nodes[] = $this->head;
    }

    protected function pushNode(Node $node): ?Edge
    {
        $this->nodes[] = $node;

        if ($this->conditionEdge) {
            $this->conditionEdge->to = $node;
            $edge = $this->edges[] = $this->conditionEdge;
            $this->conditionEdge = null;

            return $edge;
        }

        if ($this->head) {
            return $this->edges[] = new Edge(from: $this->head, to: $node);
        }

        return null;
    }

    public function push(Node $node): self
    {
        $this->pushNode($node);

        $this->head = $node;

        return $this;
    }

    public function pushTerminate(Node $node): self
    {
        $this->pushNode($node);

        $this->head = null;

        return $this;
    }

    public function pushCondition(?Expr $condition = null): self
    {
        $node = new ConditionNode;

        $this->pushNode($node);

        $this->conditionNodesStack[] = $node;

        $this->head = $node;

        $this->conditionEdge = new Edge(from: $node, conditions: $condition ? [$condition] : []);

        return $this;
    }

    public function pushConditionBranch(?Expr $condition = null): self
    {
        $this->head = $this->conditionNodesStack[count($this->conditionNodesStack) - 1];

        $this->conditionEdge = new Edge(from: $this->head, conditions: $condition ? [$condition] : [], isNegated: ! $condition);

        return $this;
    }

    public function exitCondition(): self
    {
        $conditionNode = array_pop($this->conditionNodesStack);

        $conditionEdges = collect($this->edges)->filter(fn (Edge $e) => $e->from === $conditionNode);
        [$negatedEdges, $otherEdges] = $conditionEdges->partition(fn (Edge $e) => $e->isNegated);

        if ($negatedEdge = $negatedEdges->first()) {
            $negatedEdge->conditions = $otherEdges->map->conditions->flatten()->values()->all();
        }

        $leafNodes = [];
        $nodesToTraverse = [$conditionNode];
        while ($nodesToTraverse) {
            $traversingNode = array_pop($nodesToTraverse);
            if (! ($traversingNodeSuccessors = $this->successors($traversingNode))) {
                $leafNodes[] = $traversingNode;
            } else {
                $nodesToTraverse = array_merge($nodesToTraverse, $traversingNodeSuccessors);
            }
        }

        $heads = collect($leafNodes)
            ->reject(fn (Node $n) => $n instanceof TerminateNode)
            ->unique(strict: true)
            ->values()
            ->all();

        if (! $heads && $negatedEdge) {
            $this->head = null;

            return $this;
        }

        $this->nodes[] = $mergeNode = new MergeNode;

        foreach ($heads as $head) {
            $this->edges[] = new Edge(from: $head, to: $mergeNode);
        }

        if (! $negatedEdge) { // no else!
            $this->edges[] = new Edge(
                from: $conditionNode,
                to: $mergeNode,
                conditions: $otherEdges->map->conditions->flatten()->values()->all(),
                isNegated: true,
            );
        }

        $this->head = $mergeNode;

        return $this;
    }

    public function pushTerminateMatch(Match_ $match): self
    {
        $this->pushCondition();

        foreach ($match->arms as $arm) {
            if ($arm->conds === null) { // default arm
                $this->pushConditionBranch(); // negated / else

                $this->pushTerminate(new TerminateNode(TerminationType::RETURN, $arm->body));

                continue;
            }

            foreach ($arm->conds as $cond) {
                $this->pushConditionBranch(new Expr\BinaryOp\Identical($match->cond, $cond));

                $this->pushTerminate(new TerminateNode(TerminationType::RETURN, $arm->body));
            }
        }

        $this->exitCondition();

        return $this;
    }

    public function pushAssignMatch(Variable $variable, Match_ $match): self
    {
        $this->pushCondition();

        foreach ($match->arms as $arm) {
            if ($arm->conds === null) { // default arm
                $this->pushConditionBranch(); // negated / else

                $this->push(new StatementNode(new Expr\Assign($variable, $arm->body)));

                continue;
            }

            foreach ($arm->conds as $cond) {
                $this->pushConditionBranch(new Expr\BinaryOp\Identical($match->cond, $cond));

                $this->push(new StatementNode(new Expr\Assign($variable, $arm->body)));
            }
        }

        $this->exitCondition();

        return $this;
    }

    public function predecessors(Node $node): array
    {
        return collect($this->edges)
            ->filter(fn (Edge $e) => $e->to === $node)
            ->values()
            ->map(fn (Edge $e) => $e->from)
            ->all();
    }

    public function successors(Node $node): array
    {
        return collect($this->edges)
            ->filter(fn (Edge $e) => $e->from === $node)
            ->values()
            ->map(fn (Edge $e) => $e->to)
            ->all();
    }

    public function getReachableNodes(Closure $cb): array
    {
        return collect($this->nodes)
            ->filter($cb)
            ->filter(fn (Node $n) => $this->getRootNode($n) instanceof StartNode)
            ->values()
            ->all();
    }

    private function getRootNode(Node $node): Node
    {
        $currentNode = $node;

        /** @var ?Edge $checkingEdge */
        $checkingEdge = collect($this->edges)
            ->first(fn (Edge $e) => $e->to === $node);

        while ($checkingEdge) {
            $currentNode = $checkingEdge->from;

            $checkingEdge = collect($this->edges)
                ->first(fn (Edge $e) => $e->to === $currentNode);
        }

        return $currentNode;
    }

    public function toDot(bool $indent = false): string
    {
        $dotGraph = 'digraph Flow {'.($indent ? "\n" : ' ');

        $dotEdges = collect($this->edges)
            ->map(fn (Edge $e, $i) => $e->toDot($this))
            ->map(fn (string $d) => $indent ? '  '.$d : $d)
            ->join(';'.($indent ? "\n" : ' '));
        $dotGraph .= $dotEdges.';'.($indent ? "\n" : ' ');

        $dotNodes = collect($this->nodes)
            ->map(fn (Node $n, $i) => $n->toDot($this))
            ->map(fn (string $d) => $indent ? '  '.$d : $d)
            ->join(';'.($indent ? "\n" : ' '));
        $dotGraph .= $dotNodes.';'.($indent ? "\n" : ' ');

        return $dotGraph.'}';
    }
}
