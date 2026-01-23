<?php

namespace Dedoc\Scramble\Infer\Flow;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Str;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\PrettyPrinter;

class Edge
{
    public function __construct(
        public Node $from,
        public ?Node $to = null,
        /** @var Expr[] */
        public array $conditions = [],
        public bool $isNegated = false,
    ) {}

    public function getRefinedVariableType(Nodes $nodes, string $varName): ?Type
    {
        if (! $this->conditions) {
            return null;
        }

        if ($this->isNegated) { // not yet
            return null;
        }

        $condition = $this->conditions[0] ?? null;
        if (! $condition) {
            return null;
        }

        if ($condition instanceof Identical) {
            return $this->getRefinedVariableTypeFromIdentical($condition, $nodes, $varName);
        }

        return null;
    }

    private function getRefinedVariableTypeFromIdentical(Identical $condition, Nodes $nodes, string $varName): ?Type
    {
        [$expressionBeingNarrowed, $expr] = $this->normalizeEqualityExpression($condition);
        if (! $expressionBeingNarrowed) {
            return null;
        }

        if ($expressionBeingNarrowed instanceof Expr\Variable) {
            return $this->narrowByVariableEquality($expressionBeingNarrowed, $expr, $nodes, $varName);
        }

        if ($expressionBeingNarrowed instanceof Expr\ArrayDimFetch) {
            return $this->narrowByArrayDimFetchEquality($expressionBeingNarrowed, $expr, $nodes, $varName);
        }

        return null;
    }

    private function normalizeEqualityExpression(Identical $node): array
    {
        [$narrowable, $expr] = $this->isNarrowableExpression($node->left)
            ? [$node->left, $node->right]
            : [$node->right, $node->left];

        if (! $this->isNarrowableExpression($narrowable)) {
            return [null, null];
        }

        return [$narrowable, $expr];
    }

    private function isNarrowableExpression(Expr $expr): bool
    {
        if ($expr instanceof Expr\Variable) {
            return true;
        }

        // Array dim fetch is also narrowable ($foo['bar']['baz']
        if ($expr instanceof Expr\ArrayDimFetch) {
            return $this->isNarrowableArrayDimFetchExpression($expr);
        }

        return false;
    }

    /**
     * Array dim fetch is narrowable when dimensions that are fetched are string literals. This supports nested
     * array dim fetches as well for cases like `$foo['bar']['baz']`.
     */
    private function isNarrowableArrayDimFetchExpression(Expr\ArrayDimFetch $expr): bool
    {
        return ($expr->dim instanceof String_ || $expr->dim instanceof Int_)
            && $this->isNarrowableExpression($expr->var);
    }

    private function narrowByVariableEquality(Expr\Variable $varBeingNarrowed, Expr $expr, Nodes $nodes, string $varName): ?Type
    {
        if ($varBeingNarrowed->name !== $varName) {
            return null;
        }

        return $nodes->getTypeAt($expr, $this->from);
    }

    private function narrowByArrayDimFetchEquality(Expr\ArrayDimFetch $narrowedExpr, Expr $expr, Nodes $nodes, string $varName): ?Type
    {
        [$var, $path] = $this->parseNarrowableArrayDimFetch($narrowedExpr);

        if ($var->name !== $varName) {
            return null;
        }

        $narrowedIncompleteArray = null;

        foreach (array_reverse($path) as $pathItem) {
            $narrowedIncompleteArray = new KeyedArrayType([
                new ArrayItemType_(
                    key: $pathItem,
                    value: $narrowedIncompleteArray ?? $nodes->getTypeAt($expr, $this->from),
                    isOptional: false,
                )
            ]);

            $narrowedIncompleteArray->setAttribute('isIncomplete', true);
        }

        return $narrowedIncompleteArray;
    }

    /**
     * @return array{0: Expr\Variable, 1: non-empty-array<string|int>}
     */
    private function parseNarrowableArrayDimFetch(Expr\ArrayDimFetch $narrowedExpr): array
    {
        $dims = [];
        $expr = $narrowedExpr;

        while ($expr instanceof Expr\ArrayDimFetch) {
            if (! $expr->dim) {
                throw new \LogicException('Array dim fetch without dim is not narrowable.');
            }

            $dim = $expr->dim;

            if ($dim instanceof String_) {
                $dims[] = $dim->value;
            } elseif ($dim instanceof Int_) {
                $dims[] = $dim->value;
            } else {
                throw new \LogicException('Only string or int array keys are narrowable.');
            }

            $expr = $expr->var;
        }

        if (! $expr instanceof Expr\Variable) {
            throw new \LogicException('Array dim fetch root must be a variable.');
        }

        return [$expr, array_reverse($dims)];
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
