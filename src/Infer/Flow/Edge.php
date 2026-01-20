<?php

namespace Dedoc\Scramble\Infer\Flow;

use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\PrettyPrinter;
use PhpParser\Node\Expr;

class Edge
{
    public function __construct(
        public Node $from,
        public ?Node $to = null,
        public array $conditions = [],
        public bool $isNegated = false,
    ) {}

    public function getAssertedVariableType(Nodes $nodes, Node $node, string $varName): ?Type
    {
        if (! $this->conditions) {
            return null;
        }

        if ($this->isNegated) { // not yet
            return null;
        }

        /** @var Identical|null $identicalCheck */
        $identicalCheck = collect($this->conditions)
            ->first(fn (Expr $n) => $n instanceof Identical && ($variableCheck = $this->identicalVariableCheck($n)) && $variableCheck[0] === $varName);

        if ($identicalCheck) {
            return $nodes->getTypeAt($this->identicalVariableCheck($identicalCheck)[1], $node);
        }

        return null;
    }

    /**
     * @return array{0: string, 1: Expr}
     */
    private function identicalVariableCheck(Identical $node): array|false
    {
        [$var, $expr] = $node->left instanceof Expr\Variable
            ? [$node->left, $node->right]
            : [$node->right, $node->left];

        if (! $var instanceof Expr\Variable) {
            return false;
        }

        if (! is_string($var->name)) {
            return false;
        }

        return [$var->name, $expr];
    }

    public function toDot(Nodes $nodes): string
    {
        if (! $this->to) {
            throw new \Exception('Incomplete edge, should not happen');
        }

        $phpParserExpressionPrinter = app(PrettyPrinter::class);

        $dot = $this->from->toDotId($nodes).' -> '.$this->to->toDotId($nodes);

        $label = null;
        if ($this->conditions || $this->isNegated) {
            $label = implode(' AND ', array_map(
                fn ($expr) => $phpParserExpressionPrinter->prettyPrint([$expr]),
                $this->conditions,
            ));

            if ($this->isNegated) {
                $label = '!('.$label.')';
            }
        }

        if ($label) {
            $dot .= ' [label="'.Str::replace('"', '\"', $label).'"]';
        }

        return $dot;
    }
}
