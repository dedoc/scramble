<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Extensions\Event\ReferenceResolutionEvent;
use Dedoc\Scramble\Infer\Extensions\ResolvingType;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;

class PropertyFetch implements ResolvingType
{
    public function resolve(ReferenceResolutionEvent $event): ?Type
    {
        $type = $event->type;

        if (! $type instanceof Generic || $type->name !== self::class) {
            return null;
        }

        if (count($type->templateTypes) !== 2) {
            return null;
        }

        [$objectType, $propertyNameType] = $type->templateTypes;

        if (! $objectType instanceof ObjectType || ! $propertyNameType instanceof LiteralStringType) {
            return null;
        }

        return ReferenceTypeResolver::getInstance()->resolve(
            new GlobalScope,
            new PropertyFetchReferenceType($objectType, $propertyNameType->value),
        );
    }
}
