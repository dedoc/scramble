<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\FunctionCallEvent;
use Dedoc\Scramble\Infer\Extensions\FunctionReturnTypeExtension;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Collection;

class ArrayMergeReturnTypeExtension implements FunctionReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === 'array_merge';
    }

    public function getFunctionReturnType(FunctionCallEvent $event): ?Type
    {
        $arguments = collect($event->arguments->all());

        if (! $arguments->every(fn ($arg) => $arg instanceof KeyedArrayType)) {
            return null;
        }
        /** @var Collection<array-key, KeyedArrayType> $arguments */
        $items = $arguments->flatMap->items
            // unique them by key like array_merge works
            ->reduce(function ($carry, $item) {
                $carry[$item->key] = $item;

                return $carry;
            }, []);

        return new KeyedArrayType(array_values($items));
    }
}
