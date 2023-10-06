<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\FunctionCallEvent;
use Dedoc\Scramble\Infer\Extensions\FunctionReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;

class ArrayKeysReturnTypeExtension implements FunctionReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === 'array_keys';
    }

    public function getFunctionReturnType(FunctionCallEvent $event): ?Type
    {
        $argType = $event->getArg('array', 0);

        if (!$argType instanceof ArrayType) {
            return null;
        }

        $keys = collect($argType->items)->map(fn (ArrayItemType_ $item) => $item->key);

        // assuming it is a list for now
        if ($keys->count() === 1) {
            return new ArrayType([
                new ArrayItemType_(
                    key: null,
                    value: Union::wrap([
                        new StringType(),
                        new IntegerType(),
                    ])
                )
            ]);
        }

        $numIndex = 0;
        return new ArrayType(
            array_map(function ($key) use (&$numIndex) {
                if ($key === null || is_numeric($key)) {
                    return new ArrayItemType_(
                        key: null,
                        value: TypeHelper::createTypeFromValue($numIndex++),
                    );
                }

                return new ArrayItemType_(
                    key: null,
                    value: TypeHelper::createTypeFromValue($key),
                );
            }, $keys->toArray())
        );
    }
}
