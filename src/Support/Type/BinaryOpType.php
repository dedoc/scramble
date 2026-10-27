<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralType;
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
        public bool $foldsNumericLiterals = true,
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
            && $this->foldsNumericLiterals === $type->foldsNumericLiterals
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

        if (in_array($this->operator, ['&&', '||', 'and', 'or', 'xor'], true)) {
            return $this->evaluateLogicalOperation($left, $right);
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

    private function evaluateLogicalOperation(Type $left, Type $right): Type
    {
        $leftValue = $this->truthValue($left);
        $rightValue = $this->truthValue($right);

        $value = match ($this->operator) {
            '&&', 'and' => $leftValue === false || $rightValue === false
                ? false
                : ($leftValue === true && $rightValue === true ? true : null),
            '||', 'or' => $leftValue === true || $rightValue === true
                ? true
                : ($leftValue === false && $rightValue === false ? false : null),
            'xor' => $leftValue !== null && $rightValue !== null
                ? $leftValue !== $rightValue
                : null,
        };

        return $value === null ? new BooleanType : new LiteralBooleanType($value);
    }

    private function truthValue(Type $type): ?bool
    {
        if ($type instanceof LiteralType) {
            return (bool) $type->getValue();
        }

        if ($type instanceof NullType) {
            return false;
        }

        if ($type instanceof KeyedArrayType) {
            if (! count($type->items)) {
                return false;
            }

            foreach ($type->items as $item) {
                if (! $item->isOptional && ! $item->shouldUnpack) {
                    return true;
                }
            }
        }

        return null;
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

        if ($left instanceof KeyedArrayType && $right instanceof KeyedArrayType && $shape = $this->addShapes($left, $right)) {
            $results[] = $shape;
        } elseif ($this->canBeArray($left) && $this->canBeArray($right)) {
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

        if ($this->foldsNumericLiterals && $leftNumber !== null && $rightNumber !== null) {
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

    private function addShapes(KeyedArrayType $left, KeyedArrayType $right): ?KeyedArrayType
    {
        $items = $this->itemsByKey($left);
        $rightItems = $this->itemsByKey($right);

        if ($items === null || $rightItems === null) {
            return null;
        }

        foreach ($rightItems as $key => $rightItem) {
            $leftItem = $items[$key] ?? null;

            if (! $leftItem) {
                $items[$key] = $rightItem;
            } elseif ($leftItem->isOptional) {
                $items[$key] = new ArrayItemType_(
                    $leftItem->key,
                    TypeHelper::mergeTypes($leftItem->value, $rightItem->value),
                    $rightItem->isOptional,
                    keyType: $leftItem->keyType,
                );
            }
        }

        return new KeyedArrayType(array_values($items), array_is_list($items));
    }

    /**
     * @return array<array-key, ArrayItemType_>|null
     */
    private function itemsByKey(KeyedArrayType $shape): ?array
    {
        $items = [];

        foreach ($shape->items as $item) {
            if ($item->key === null || $item->shouldUnpack) {
                return null;
            }

            $items[$item->key] = $item;
        }

        return $items;
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
