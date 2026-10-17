<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Support\Type\Contracts\LiteralType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;

class LiteralTypeEvaluator
{
    public function evaluate(Type $type): mixed
    {
        $type = $this->pickFirstAvailableLiteralType($type);

        if (! $type) {
            return null;
        }

        dump([
            $this->doEvaluate($type),
            $type,
        ]);

        return $this->doEvaluate($type);
    }

    private function pickFirstAvailableLiteralType(Type $type): ?Type
    {
        $types = $type instanceof Union ? $type->types : [$type];

        foreach ($types as $type) {
            if (
                $type instanceof LiteralType
                || $type instanceof NullType
                || $type instanceof KeyedArrayType
            ) {
                return $type;
            }
        }

        return null;

        throw new \RuntimeException('Cannot pick evaluable type');
    }

    private function doEvaluate(Type $type): mixed
    {
        if ($type instanceof NullType) {
            return null;
        }

        if ($type instanceof LiteralType) {
            return $type->getValue();
        }

        if (! $type instanceof KeyedArrayType) {
            return null;
        }

        /** @var KeyedArrayType $type */
        $result = [];
        foreach ($type->items as $item) {
            if ($item->value instanceof UnknownType) {
                continue;
            }

            $value = $this->doEvaluate($item->value);

            if ($item->key === null) {
                $result[] = $value;
            } else {
                $result[$item->key] = $value;
            }
        }

        return $result;
    }
}
