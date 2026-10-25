<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Extensions\ResolvingType;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Contracts\Support\Arrayable;

class InRuleValuesResolver implements ResolvingType
{
    public function resolve(ReferenceResolutionEvent $event): ?Type
    {
        $type = $event->type;
        if (! $type instanceof Generic || $type->name !== self::class || count($type->templateTypes) !== 2) {
            return null;
        }

        [$values, $arguments] = $type->templateTypes;

        if ($values instanceof ObjectType && $values->isInstanceOf(Arrayable::class)) {
            return ReferenceTypeResolver::getInstance()->resolve(
                new GlobalScope,
                new MethodCallReferenceType($values, 'toArray', []),
            );
        }

        if ($values instanceof KeyedArrayType || $values instanceof ArrayType) {
            return $values;
        }

        if (! $arguments instanceof KeyedArrayType) {
            return null;
        }

        return new KeyedArrayType(array_map(
            fn (ArrayItemType_ $item) => new ArrayItemType_(null, $item->value),
            $arguments->items,
        ));
    }
}
