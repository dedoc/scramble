<?php

namespace Dedoc\Scramble\Infer\Flow;

use Closure;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Type\VoidType;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use WeakMap;

class Nodes
{
    /** @var Node[] */
    public array $nodes = [];

    /** @var Edge[] */
    public array $edges = [];

    public ?Node $head;

    /** @var Node[] */
    private array $conditionNodesStack = [];

    public ?Edge $conditionEdge = null;

    /** @var array<string, true> */
    protected array $resolvingVariables = [];

    public function __construct(private ExpressionTypeInferrer $expressionTypeInferrer)
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
        if (! $conditionNode) {
            throw new \Exception('Should not happen');
        }

        $conditionEdges = collect($this->edges)->filter(fn (Edge $e) => $e->from === $conditionNode);
        [$negatedEdges, $otherEdges] = $conditionEdges->partition(fn (Edge $e) => $e->isNegated)->all();

        if ($negatedEdge = $negatedEdges->first()) {
            $negatedEdge->conditions = $otherEdges->map->conditions->flatten()->values()->all(); // @phpstan-ignore assign.propertyType
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
                conditions: $otherEdges->map->conditions->flatten()->values()->all(), // @phpstan-ignore argument.type
                isNegated: true,
            );
        }

        $this->head = $mergeNode;

        return $this;
    }

    /** @return Node[] */
    private function successors(Node $node): array
    {
        return collect($this->edges)
            ->filter(fn (Edge $e) => $e->from === $node)
            ->values()
            ->map(fn (Edge $e): Node => $e->to) // @phpstan-ignore return.type
            ->all();
    }

    /** @return Node[] */
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

    /**
     * @param  Closure(Type $t): bool  $cb
     * @return list<Node>
     */
    public function findValueOriginsByExitType(Closure $cb): array
    {
        /** @var TerminateNode[] $nodes */
        $nodes = $this
            ->getReachableNodes(fn (Node $n) => $n instanceof TerminateNode && $n->kind === TerminationKind::RETURN);

        $origins = [];
        foreach ($nodes as $node) {
            $nodeValueOrigins = $this->findValueOrigins($node);

            foreach ($nodeValueOrigins as $nodeValueOrigin) {
                $expression = $nodeValueOrigin instanceof TerminateNode
                    ? $nodeValueOrigin->value
                    : ($nodeValueOrigin instanceof StatementNode
                        && $nodeValueOrigin->parserNode instanceof Expression
                        && $nodeValueOrigin->parserNode->expr instanceof Expr\Assign ? $nodeValueOrigin->parserNode->expr->expr : null);

                $type = $expression ? $this->getTypeAt($expression, $nodeValueOrigin) : new VoidType;

                if ($cb($type)) {
                    $origins[] = $nodeValueOrigin;
                }
            }
        }

        return $origins;
    }

    /** @return list<Node> */
    protected function findValueOrigins(TerminateNode $node): array
    {
        if (! $node->value) {
            return [$node];
        }

        if (! $node->value instanceof Variable || ! is_string($node->value->name)) {
            return [$node];
        }

        $origins = [];
        $stack = [$node];
        $visited = new WeakMap;
        while ($stack) {
            /** @var Node $current */
            $current = array_pop($stack);

            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            foreach ($this->incomingEdges($current) as $edge) {
                $prev = $edge->from;

                if ($prev->definesVariable($node->value->name)) {
                    $origins[] = $prev;

                    continue;
                }

                $stack[] = $prev;
            }
        }

        return $origins;
    }

    /** @return Edge[] */
    private function incomingEdges(Node $node): array
    {
        return collect($this->edges)
            ->filter(fn (Edge $e) => $e->to === $node)
            ->values()
            ->all();
    }

    public function getTypeAt(Expr $expr, Node $node): Type
    {
        return $this->expressionTypeInferrer->infer(
            expr: $expr,
            variableTypeGetter: fn (Expr\Variable $n) => $this->resolveNarrowedVariableTypeAtLocation($n, $node),
        );
    }

    private function resolveNarrowedVariableTypeAtLocation(Expr\Variable $var, Node $node): Type
    {
        $definedTypeAtLocation = $this->resolveVariableTypeAt($var, $node);
        $narrowedType = $this->narrowType($definedTypeAtLocation, $var, $node);

        // @todo checking for `isIncomplete` is incredibly important: we don't want to report that some variables has some type based ONLY on narrowing.
        if ($narrowedType/* && ! $narrowedType->getAttribute('isIncomplete') */) {
            return $narrowedType;
        }

        return $definedTypeAtLocation;
    }

    private function resolveVariableTypeAt(Expr\Variable $var, Node $node): Type
    {
        $varName = $var->name;
        if (! is_string($varName)) {
            return new UnknownType;
        }

        if ($node instanceof StartNode) {
            // @todo parameter type!
            return new UnknownType;
        }

        if ($node->definesVariable($varName)) {
            // When the variable in question is being resolved (for example, `$a = $a`), the following call will
            // return `null`. In such cases we just want to keep looking up, so the execution doesn't stuck here.
            $type = $this->resolveVariableTypeAtAssignmentNode($varName, $node);

            if ($type) {
                return $type;
            }
        }

        $types = [];
        foreach ($this->incomingEdges($node) as $incomingEdge) {
            $types[] = $this->resolveVariableTypeAt($var, $incomingEdge->from);
        }

        return Union::wrap($types);
    }

    private function resolveVariableTypeAtAssignmentNode(string $varName, Node $node): ?Type
    {
        if (! $node->definesVariable($varName)) {
            throw new \Exception('Should not happen');
        }

        $key = $varName.'@'.spl_object_id($node);

        $isAlreadyResolvingVariable = array_key_exists($key, $this->resolvingVariables);

        if ($isAlreadyResolvingVariable) {
            return null;
        }

        /** @var Expr\Assign $assignment */
        $assignment = $node->getParserNode()->expr;

        try {
            $this->resolvingVariables[$key] = true;

            return $this->getTypeAt($assignment->expr, $node);
        } finally {
            unset($this->resolvingVariables[$key]);
        }
    }

    private function narrowType(Type $type, Expr\Variable $var, Node $node): ?Type
    {
        $varName = $var->name;
        if (! is_string($varName)) {
            return null;
        }

        if ($node instanceof MergeNode || $node instanceof StartNode || $node->definesVariable($varName)) {
            return null;
        }

        $incomingEdge = $this->incomingEdges($node)[0] ?? null;
        if (! $incomingEdge) {
            return null;
        }

        if ($t = $incomingEdge->getRefinedVariableType($this, $varName)) {
            return $t;
        }

        return $this->narrowType($type, $var, $incomingEdge->from);
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
