<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Attributes\Hidden;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\AttributeDefinition;
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
use ReflectionClass;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

class PlainObjectToSchema extends TypeToSchemaExtension
{
    /** @var array<string, false|Type> */
    private array $cache = [];

    public function shouldHandle(Type $type)
    {
        $isObject = $type instanceof ObjectType && class_exists($type->name);

        if (! $isObject) {
            return false;
        }

        if (is_a($type->name, Response::class, true)) {
            return false;
        }

        if (! (new ReflectionClass($type->name))->isInstantiable()) {
            return false;
        }

        if (! $this->getSerializedType($type)) {
            return false;
        }

        return true;
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type)
    {
        if ($serializedType = $this->getSerializedType($type)) {
            return $this->openApiTransformer->transform($serializedType);
        }

        return null;
    }

    private function getSerializedType(ObjectType $type): ?Type
    {
        $cacheKey = $type->toString();

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey] ?: null;
        }

        $serializedType = $this->getFreshSerializedType($type);

        $this->cache[$cacheKey] = $serializedType ?: false;

        return $serializedType;
    }

    private function getFreshSerializedType(ObjectType $type): ?Type
    {
        $definition = $this->infer->index->getClass($type->name);

        if ($jsonSerializableType = $this->getJsonSerializableType($definition, $type)) {
            return $jsonSerializableType;
        }

        if ($publicPropertiesType = $this->getSerializedPublicPropertiesType($definition, $type)) {
            return $publicPropertiesType;
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

            if ($propertyDefinition->getAttributes(Hidden::class, AttributeDefinition::IS_INSTANCEOF)) {
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

        if (! $this->getSerializedType($type)) {
            return null;
        }

        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }
}
