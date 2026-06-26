<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\InferExtensions\Concerns\ExtractsLiteralArrayKeys;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\OffsetAccessType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Arr;

class ArrStaticMethodReturnTypeExtension implements StaticMethodReturnTypeExtension
{
    use ExtractsLiteralArrayKeys;

    public function shouldHandle(string $name): bool
    {
        return $name === Arr::class;
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'except' => $this->handleExcept($event),
            'only' => $this->handleOnly($event),
            default => null,
        };
    }

    private function handleExcept(StaticMethodCallEvent $event): ?Type
    {
        $keyTypes = $this->extractKeyTypesFromEvent($event, 'keys', 1);

        if ($keyTypes === null) {
            return null;
        }

        return $this->unsetKeysFromType($event->getArg('array', 0), $keyTypes);
    }

    private function handleOnly(StaticMethodCallEvent $event): ?Type
    {
        $arrayType = $event->getArg('array', 0);
        $keyTypes = $this->extractKeyTypesFromEvent($event, 'keys', 1);

        if ($keyTypes === null) {
            return null;
        }

        $items = [];

        foreach ($keyTypes as $keyType) {
            if (! $literalKey = $this->getLiteralKey($keyType)) {
                return null;
            }

            $items[] = new ArrayItemType_(
                key: $literalKey,
                value: new OffsetAccessType($arrayType, $keyType),
            );
        }

        return new KeyedArrayType($items);
    }
}
