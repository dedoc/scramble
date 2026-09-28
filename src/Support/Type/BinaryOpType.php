<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
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

        if ($this->operator === '+') {
            return $this->evaluateAddition($left, $right);
        }

        return $this->evaluateNumericArithmetic($left, $right) ?: new NeverType;
    }

    private function unwrap(Type $type): Type
    {
        if ($type instanceof TemplateType && $type->is) {
            return $this->unwrap($type->is);
        }

        return $type;
    }

    private function evaluateAddition(Type $left, Type $right): Type
    {
        $results = [];

        if ($numericResult = $this->evaluateNumericArithmetic($left, $right)) {
            array_push(
                $results,
                ...($numericResult instanceof Union ? $numericResult->types : [$numericResult]),
            );
        }

        if ($this->canBeArray($left) && $this->canBeArray($right)) {
            $results[] = new ArrayType;
        }

        return count($results)
            ? Union::wrap($results)
            : new NeverType;
    }

    private function evaluateNumericArithmetic(Type $left, Type $right): ?Type
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

        $leftNumeric = $this->numericOperand($left);
        $rightNumeric = $this->numericOperand($right);

        if ($leftNumeric === null || $rightNumeric === null) {
            return null;
        }

        if ($this->operator === '%') {
            return new IntegerType;
        }

        $eitherIsFloat = $leftNumeric === 'float' || $rightNumeric === 'float';
        $bothAreInt = $leftNumeric === 'int' && $rightNumeric === 'int';

        if ($this->operator === '/' || $this->operator === '**') {
            return $eitherIsFloat
                ? new FloatType
                : $this->intOrFloat();
        }

        if ($eitherIsFloat) {
            return new FloatType;
        }

        if ($bothAreInt) {
            return new IntegerType;
        }

        return $this->intOrFloat();
    }

    private function canBeArray(Type $type): bool
    {
        return $type instanceof ArrayType
            || $type instanceof KeyedArrayType
            || $type instanceof UnknownType
            || $type instanceof MixedType
            || ($type instanceof TemplateType && $this->canBeArray($type->is ?: new MixedType));
    }

    /**
     * Numeric kind of an operand assuming the operation succeeds.
     *
     * @return 'int'|'float'|'number'|null
     */
    private function numericOperand(Type $type): ?string
    {
        if ($type instanceof IntegerType || $type instanceof BooleanType || $type instanceof NullType) {
            return 'int';
        }

        if ($type instanceof FloatType) {
            return 'float';
        }

        if (
            $type instanceof ArrayType
            || $type instanceof KeyedArrayType
            || $type instanceof ObjectType
            || $type instanceof FunctionLikeType
            || $type instanceof VoidType
            || $type instanceof NeverType
            || $type instanceof MissingType
        ) {
            return null;
        }

        return 'number';
    }

    private function intOrFloat(): Type
    {
        return Union::wrap([new IntegerType, new FloatType]);
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

        if ($type instanceof LiteralBooleanType) {
            return $type->value ? 1 : 0;
        }

        if ($type instanceof NullType) {
            return 0;
        }

        return null;
    }
}
