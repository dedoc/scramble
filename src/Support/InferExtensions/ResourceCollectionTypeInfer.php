<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\PropertyTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;

class ResourceCollectionTypeInfer implements MethodReturnTypeExtension, PropertyTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(ResourceCollection::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'toArray' => $this->getToArrayReturnType($event),
            default => null,
        };
    }

    public function getPropertyType(PropertyFetchEvent $event): ?Type
    {
        return match ($event->name) {
            'collection' => $this->getCollectionType($event->getInstance(), $event->getDefinition()),
            default => null,
        };
    }

    private function getToArrayReturnType(MethodCallEvent $event): ?Type
    {
        $parentType = $this->getCollectionType($event->getInstance(), $event->getDefinition());

        if ($event->methodDefiningClassName === ResourceCollection::class) {
            return $parentType;
        }

        $realType = $event->getDefinition()->getMethodDefinition('toArray')?->type->getReturnType();
        if ($realType instanceof UnknownType) {
            /**
             * When inferred return type of `toArray` method cannot be inferred, we'd like to fall back to
             * the default behavior of ResourceCollection, so there is still SOME information.
             */
            return $parentType;
        }

        return $realType;
    }

    private function getCollectionType(ObjectType $type, ClassDefinition $definition): ArrayType
    {
        if ($type instanceof Generic && $instanceCollectedType = $this->getCollectedInstanceType($type)) {
            return new ArrayType($instanceCollectedType);
        }

        $propertyOrGuessedClassNameType = $this->getCollectingClassType($definition);
        if (! $propertyOrGuessedClass = $propertyOrGuessedClassNameType?->value) {
            return new ArrayType(new UnknownType('Cannot get collection type'));
        }

        return new ArrayType(new Generic($propertyOrGuessedClass, [new UnknownType]));
    }

    public function getBasicCollectionType(ClassDefinition $classDefinition)
    {
        $collectingClassType = $this->getCollectingClassType($classDefinition);

        if (! $collectingClassType) {
            return new UnknownType('Cannot find a type of the collecting class.');
        }

        return new ArrayType(value: new ObjectType($collectingClassType->value));
    }

    public function getCollectingClassType(ClassDefinition $classDefinition): ?LiteralStringType
    {
        $collectingClassDefinition = $classDefinition->getPropertyDefinition('collects');

        $collectingClassType = $collectingClassDefinition?->defaultType;

        if (! $collectingClassType instanceof LiteralStringType) {
            if (
                str_ends_with($classDefinition->name, 'Collection') &&
                (class_exists($class = Str::replaceLast('Collection', '', $classDefinition->name)) ||
                    class_exists($class = Str::replaceLast('Collection', 'Resource', $classDefinition->name)))
            ) {
                $collectingClassType = new LiteralStringType($class);
            } else {
                return null;
            }
        }

        return $collectingClassType;
    }

    public function getCollectedInstanceType(ObjectType $type): ?Type
    {
        if (! $type instanceof Generic) {
            return null;
        }

        $collectsClassNameType = $type->templateTypes[/* TCollects */ 2] ?? null;
        if (! $collectsClassNameType instanceof LiteralStringType) {
            return null;
        }

        return new Generic($collectsClassNameType->value, [new UnknownType]);
    }
}
