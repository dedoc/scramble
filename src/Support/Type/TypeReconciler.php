<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralFloatType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;

class TypeReconciler
{
    /**
     * @param Type[] $types
     */
    public function reconcile(array $types): Type
    {
        $items = $types;
        $changed = true;
        while ($changed) {
            $changed = false;

            for ($i = 0; $i < count($items); $i++) {
                for ($j = $i + 1; $j < count($items); $j++) {
                    $a = $items[$i];
                    $b = $items[$j];

                    $merged = $this->reconcilePair($a, $b) ?: $this->reconcilePair($b, $a);

                    if ($merged !== null) {
                        unset($items[$i], $items[$j]);
                        $items[] = $merged;
                        $items = array_values($items);
                        $changed = true;
                        continue 3;
                    }
                }
            }
        }

        return Union::wrap($items);
    }

    private function reconcilePair(Type $a, Type $b): ?Type
    {
        // mixed|* -> mixed
        if ($a instanceof MixedType) {
            return new MixedType;
        }

        // true|false -> bool
        if (
            ($a instanceof LiteralBooleanType && $a->value === true)
            && ($b instanceof LiteralBooleanType && $b->value === false)
        ) {
            return new BooleanType();
        }

        // bool|false or bool|true -> bool
        if (
            ($a instanceof BooleanType && ! $a instanceof LiteralBooleanType)
            && $b instanceof LiteralBooleanType
        ) {
            return new BooleanType();
        }

        // int|42 -> int
        if (
            ($a instanceof IntegerType && ! $a instanceof LiteralIntegerType)
            && $b instanceof LiteralIntegerType
        ) {
            return new IntegerType();
        }

        // float|42 -> float
        if (
            ($a instanceof FloatType && ! $a instanceof LiteralFloatType)
            && ($b instanceof LiteralFloatType || $b instanceof LiteralIntegerType)
        ) {
            return new FloatType();
        }

        // string|'wow' -> string
        if (
            ($a instanceof StringType && ! $a instanceof LiteralStringType)
            && $b instanceof LiteralStringType
        ) {
            return new StringType();
        }

        return null;
    }
}
