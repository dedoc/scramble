<?php

namespace Dedoc\Scramble\Support\InferExtensions\Concerns;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\OffsetUnsetType;
use Dedoc\Scramble\Support\Type\Type;

trait ExtractsLiteralArrayKeys
{
    /**
     * @return null|list<Type>
     */
    protected function extractKeyTypes(Type $keysType): ?array
    {
        if ($keysType instanceof LiteralString || $keysType instanceof LiteralIntegerType) {
            return [$keysType];
        }

        if ($keysType instanceof KeyedArrayType) {
            $keyTypes = [];

            foreach ($keysType->items as $item) {
                if (! $item->value instanceof LiteralString && ! $item->value instanceof LiteralIntegerType) {
                    return null;
                }

                $keyTypes[] = $item->value;
            }

            return $keyTypes;
        }

        return null;
    }

    protected function getLiteralKey(Type $keyType): string|int|null
    {
        if ($keyType instanceof LiteralString || $keyType instanceof LiteralIntegerType) {
            return $keyType->getValue();
        }

        return null;
    }

    protected function unsetKeysFromType(Type $type, array $keyTypes): Type
    {
        $result = $type;

        foreach ($keyTypes as $keyType) {
            $result = new OffsetUnsetType(
                $result,
                new KeyedArrayType([
                    new ArrayItemType_(null, value: $keyType),
                ]),
            );
        }

        return $result;
    }

    protected function extractKeyTypesFromEvent(MethodCallEvent|StaticMethodCallEvent $event, string $name, int $position): ?array
    {
        if ($keyTypes = $this->extractKeyTypes($event->getArg($name, $position))) {
            return $keyTypes;
        }

        $keyTypes = [];

        foreach ($event->arguments->all() as $argPosition => $argumentType) {
            if (! is_int($argPosition) || $argPosition < $position) {
                continue;
            }

            if (! $argumentType instanceof LiteralString && ! $argumentType instanceof LiteralIntegerType) {
                break;
            }

            $keyTypes[] = $argumentType;
        }

        return count($keyTypes) ? $keyTypes : null;
    }
}
