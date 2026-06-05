<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Attributes\Hidden;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\PropertyVisibility;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Response;
use ReflectionClass;
use stdClass;

class PlainObjectToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        $isObject = $type instanceof ObjectType && class_exists($type->name);

        if (! $isObject) {
            return false;
        }

        if (is_a($type->name, \Symfony\Component\HttpFoundation\Response::class, true)) {
            return false;
        }

        return (new ReflectionClass($type->name))->isInstantiable();
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type)
    {
        $definition = $this->infer->analyzeClass($type->name);

        if ($jsonSerializableType = $this->getJsonSerializableType($definition, $type)) {
            return $this->openApiTransformer->transform($jsonSerializableType);
        }

        if ($publicPropertiesType = $this->getSerializedPublicPropertiesType($definition, $type)) {
            return $this->openApiTransformer->transform($publicPropertiesType);
        }

        return null;
    }

    private function getJsonSerializableType(ClassDefinition $definition, ObjectType $type): ?Type
    {
        if (! $definition->getMethod('jsonSerialize')) {
            return null;
        }

        return ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new MethodCallReferenceType($type, 'jsonSerialize', [])
            );
    }

    /** @see Infer\Definition\ClassPropertyDefinition */
    private function getSerializedPublicPropertiesType(ClassDefinition $definition, ObjectType $type): ?Type
    {
        $items = [];
        foreach ($definition->getData()->properties as $name => $propertyDefinition) {
            if ($propertyDefinition->visibility !== PropertyVisibility::Public) {
                continue;
            }

            if ($propertyDefinition->hasAttribute(Hidden::class)) {
                continue;
            }

            $item = new ArrayItemType_(
                $name,
                ReferenceTypeResolver::getInstance()->resolve(
                    new GlobalScope,
                    new PropertyFetchReferenceType($type, $name),
                ),
            );

            if ($docNode = $propertyDefinition->getDocNode()) {
                $item->setAttribute('docNode', $docNode);
            }

            $items[] = $item;
        }

        if (! count($items)) {
            return null;
        }

        return new KeyedArrayType($items);
    }

    public function reference(ObjectType $type)
    {
        if (ltrim($type->name, '\\') === stdClass::class) {
            return null;
        }

        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }
}
