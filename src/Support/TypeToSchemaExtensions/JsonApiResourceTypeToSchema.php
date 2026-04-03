<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Reflection\ReflectionJsonApiResource;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\Generator\Types as OpenApiType;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class JsonApiResourceTypeToSchema extends JsonResourceTypeToSchema
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(JsonApiResource::class);
    }

    /**
     * @param ObjectType $type
     */
    public function toSchema(Type $type)
    {
        $type = $this->normalizeType($type);

        $reflection = ReflectionJsonApiResource::createForClass($type->name);

        $schema = (new OpenApiType\ObjectType())
            ->addProperty(
                'id',
                $this->openApiTransformer->transform($reflection->getIdType($type)),
            )
            ->addProperty(
                'type',
                $this->openApiTransformer->transform($reflection->getTypeType($type)),
            )
            ->setRequired(['id', 'type']);

        $this->attachAttributes($schema, $reflection);
        $this->attachRelationships($schema, $reflection);

        return $schema;
    }

    private function attachAttributes(OpenApiType\ObjectType $schema, ReflectionJsonApiResource $reflection): void
    {
        if (! $attributes = $reflection->getAttributesType()) {
            return;
        }

        /*
         * Due to sparse fields, all attributes are optional by default.
         */
        foreach ($attributes->items as $item) {
            if ($item->value instanceof FunctionType) {
                $item->value = $item->value->getReturnType();
                $item->isOptional = true;

                continue;
            }

            if (! is_string($item->key)) {
                continue;
            }
            $item->isOptional = ! $this->isAttributeRequired($item->key);
        }

        $schema
            ->addProperty('attributes', $this->openApiTransformer->transform($attributes))
            ->addRequired(['attributes']);
    }

    private function attachRelationships(OpenApiType\ObjectType $schema, ReflectionJsonApiResource $reflection): void
    {
        if (! $relationships = $reflection->getRelationshipsType()) {
            return;
        }

        $relationships = clone $relationships;
        foreach ($relationships->items as $index => $item) {
            $item = clone $item;
            $item->isOptional = true;

            if ($item->value->isInstanceOf(AnonymousResourceCollection::class)) {
                $item->value = new KeyedArrayType([
                    new ArrayItemType_('data', new ArrayType(
                        $this->buildRelationshipIdentifierType($this->normalizeType(
                            $item->value->templateTypes[2 /* TResource */] // @todo error here
                        ))
                    ))
                ]);
            } elseif ($item->value->isInstanceOf(JsonApiResource::class)) {
                $item->value = new KeyedArrayType([
                    new ArrayItemType_(
                        'data',
                        $this->buildRelationshipIdentifierType($this->normalizeType($item->value))
                    )
                ]);
            } else {
                // @todo diagnostics
            }

            $relationships->items[$index] = $item;
        }

        $schema->addProperty('relationships', $this->openApiTransformer->transform($relationships));
    }

    private function buildRelationshipIdentifierType(Generic $relationshipType): KeyedArrayType
    {
        $reflection = ReflectionJsonApiResource::createForClass($relationshipType->name);

        return new KeyedArrayType([
            new ArrayItemType_('id', $reflection->getIdType($relationshipType)),
            new ArrayItemType_('type', $reflection->getTypeType($relationshipType)),
        ]);
    }

    private function isAttributeRequired(string $name): bool
    {
        return false;
    }

    protected function normalizeType(ObjectType $type): Generic
    {
        if ($type instanceof Generic) {
            return $type;
        }

        $inferredCreationType = ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new NewCallReferenceType($type->name, []),
            );

        if ($inferredCreationType instanceof Generic) {
            return $inferredCreationType;
        }

        return new Generic($type->name, [new UnknownType]);
    }
}
