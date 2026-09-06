<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\Literal\LiteralFloatType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Throwable;

class BinaryOpType extends AbstractType implements LateResolvingType
{
    public function __construct(
        public Type $left,
        public Type $right,
        public string $operator,
    ) {}

    public function nodes(): array
    {
        return ['left', 'right'];
    }

    public function resolve(): Type
    {
        return $this->evaluate($this->left, $this->right);
    }

    public function isResolvable(): bool
    {
        return TypeHelper::isResolvable($this->left)
            && TypeHelper::isResolvable($this->right);
    }

    public function isSame(Type $type)
    {
        return $type instanceof static
            && $this->operator === $type->operator
            && $this->left->isSame($type->left)
            && $this->right->isSame($type->right);
    }

    public function toString(): string
    {
        return $this->left->toString().$this->operator.$this->right->toString();
    }

    private function evaluate(Type $left, Type $right): Type
    {
        $left = $this->unwrap($left);
        $right = $this->unwrap($right);

        if ($left instanceof Union || $right instanceof Union) {
            $results = [];

            foreach ($left instanceof Union ? $left->types : [$left] as $leftType) {
                foreach ($right instanceof Union ? $right->types : [$right] as $rightType) {
                    $results[] = $this->evaluate($leftType, $rightType);
                }
            }

            return Union::wrap($results)->widen();
        }

        if ($this->operator === '.') {
            return ConcatenatedStringType::fromParts([$left, $right]);
        }

        return $this->evaluateArithmetic($left, $right);
    }

    private function unwrap(Type $type): Type
    {
        if ($type instanceof TemplateType && $type->is) {
            return $this->unwrap($type->is);
        }

        return $type;
    }

    private function evaluateArithmetic(Type $left, Type $right): Type
    {
        $leftNumber = $this->literalNumber($left);
        $rightNumber = $this->literalNumber($right);

        if ($leftNumber !== null && $rightNumber !== null) {
            $value = $this->applyOperator($leftNumber, $rightNumber);

            if ($value !== null) {
                return is_int($value)
                    ? new LiteralIntegerType($value)
                    : new LiteralFloatType($value);
            }
        }

        $leftIsInt = $left instanceof IntegerType;
        $rightIsInt = $right instanceof IntegerType;
        $leftIsFloat = $left instanceof FloatType;
        $rightIsFloat = $right instanceof FloatType;

        if (! ($leftIsInt || $leftIsFloat) || ! ($rightIsInt || $rightIsFloat)) {
            return new UnknownType;
        }

        if ($this->operator === '%') {
            return new IntegerType;
        }

        if ($this->operator === '/' || $this->operator === '**') {
            if ($leftIsInt && $rightIsInt) {
                return Union::wrap([new IntegerType, new FloatType]);
            }

            return new FloatType;
        }

        if ($leftIsFloat || $rightIsFloat) {
            return new FloatType;
        }

        return new IntegerType;
    }

    private function applyOperator(int|float $left, int|float $right): int|float|null
    {
        try {
            return match ($this->operator) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $right == 0 ? null : $left / $right,
                '%' => $right == 0 ? null : $left % $right,
                '**' => $left ** $right,
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    private function literalNumber(Type $type): int|float|null
    {
        if ($type instanceof LiteralIntegerType) {
            return $type->value;
        }

        if ($type instanceof LiteralFloatType) {
            return $type->value;
        }

        return null;
    }
}
