<?php

namespace Dedoc\Scramble\Infer\Flow;

use Closure;
use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter;

class Nodes
{
    /** @var Node[] */
    public array $nodes = [];

    public array $edges = [];

    public ?Node $head;

    public function __construct()
    {
        $this->head = new StartNode;
        $this->nodes[] = $this->head;
    }

    private array $conditionNodesStack = [];

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

    public ?Edge $conditionEdge = null;

    public function pushCondition(ConditionNode $node): self
    {
        $this->pushNode($node);

        $this->conditionNodesStack[] = $node;

        $this->head = $node;

        $this->conditionEdge = new Edge(from: $node, conditions: [$node->parserNode->cond]);

        return $this;
    }

    public function pushConditionBranch(?Expr $condition = null): self
    {
        $this->head = $this->conditionNodesStack[count($this->conditionNodesStack) - 1];

        $this->conditionEdge = new Edge(from: $this->head, conditions: $condition ? [$condition] : [], isNegated: !$condition);

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

        $this->nodes[] = $mergeNode = new MergeNode();

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
            ->filter(fn (Node $n) => count($this->predecessors($n)) > 0)
            ->filter($cb)
            ->values()
            ->all();
    }

    private function printNode(Node $n): string
    {
        $phpParserExpressionPrinter = app(PrettyPrinter::class);

        return match ($n::class) {
            StartNode::class => 'S',
            TerminateNode::class => match ($n->type) {
                    TerminationType::RETURN => 'Ret',
                    TerminationType::THROW => 'Throw',
                } . ($n->value ? ' '.$phpParserExpressionPrinter->prettyPrintExpr($n->value) : ' VOID'),
            UnknownNode::class => 'Unk',
            ConditionNode::class => 'if',
            MergeNode::class => 'M',
        };
    }

    public function debug(): string
    {
        $debugPrintNode = fn (Node $n) => $this->printNode($n).'('.spl_object_id($n).')';
        $debug = '';
        foreach ($this->nodes as $node) {
            $debug .= $debugPrintNode($node).($node instanceof TerminateNode ? '' : ': ');
            $debug .= trim(implode(
                ', ',
                array_map(
                    $debugPrintNode,
                    $this->successors($node)
                ),
            ));
            $debug .= "\n";
        }
        return $debug;
    }

    public function toDot(bool $indent = false): string
    {
        $dotGraph = "digraph Flow {".($indent ? "\n" : ' ');

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

        return $dotGraph."}";
    }
}
