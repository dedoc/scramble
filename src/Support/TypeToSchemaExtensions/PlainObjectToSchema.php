<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

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

class PlainObjectToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType;
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

        return $this->openApiTransformer->transform(
            $this->getSerializedPublicPropertiesType($definition, $type)
        );
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
    private function getSerializedPublicPropertiesType(ClassDefinition $definition, ObjectType $type): Type
    {
        $resolver = ReferenceTypeResolver::getInstance();
        $scope = new GlobalScope;

        $items = [];
        foreach ($definition->getData()->properties as $name => $propertyDefinition) {
            if ($propertyDefinition->visibility !== PropertyVisibility::Public) {
                continue;
            }

            $items[] = new ArrayItemType_(
                $name,
                $resolver->resolve($scope, new PropertyFetchReferenceType($type, $name)),
            );
        }

        return new KeyedArrayType($items);
    }

    public function reference(ObjectType $type)
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }
}
