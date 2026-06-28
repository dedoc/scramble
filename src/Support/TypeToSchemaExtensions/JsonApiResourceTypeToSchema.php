<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Reflection\ReflectionJsonApiResource;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types as OpenApiType;
use Dedoc\Scramble\Support\InferExtensions\JsonApiResourceMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeManagers\JsonApiResourceTypeManager;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class JsonApiResourceTypeToSchema extends JsonResourceTypeToSchema
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(JsonApiResource::class);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type): OpenApiType\ObjectType
    {
        $type = app(JsonApiResourceTypeManager::class)->normalizeType($type);

        $reflection = ReflectionJsonApiResource::createForClass($type->name);

        $schema = (new OpenApiType\ObjectType)
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
        $this->attachLinks($schema, $reflection);
        $this->attachMeta($schema, $reflection);

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
            }

            $item->isOptional = true;
        }

        $schema->addProperty('attributes', $this->openApiTransformer->transform($attributes));
    }

    private function attachRelationships(OpenApiType\ObjectType $schema, ReflectionJsonApiResource $reflection): void
    {
        $relationshipItems = $reflection->getRelationshipItems();

        if (! $relationshipItems) {
            return;
        }

        $items = array_map(function ($relationship) {
            $identifierType = $this->buildRelationshipIdentifierType($this->normalizeType($relationship->resourceType));

            $value = $relationship->isCollection
                ? new KeyedArrayType([new ArrayItemType_('data', new ArrayType($identifierType))])
                : new KeyedArrayType([new ArrayItemType_('data', $identifierType)]);

            return tap(
                new ArrayItemType_($relationship->name, $value, isOptional: true),
                fn (ArrayItemType_ $t) => $t->setAttribute('docNode', $relationship->phpDoc),
            );
        }, $relationshipItems);

        $schema->addProperty('relationships', $this->openApiTransformer->transform(new KeyedArrayType($items)));
    }

    private function attachLinks(OpenApiType\ObjectType $schema, ReflectionJsonApiResource $reflection): void
    {
        if (! $links = $reflection->getLinksType()) {
            return;
        }

        $schema
            ->addProperty('links', $this->openApiTransformer->transform($links))
            ->addRequired(['links']);
    }

    private function attachMeta(OpenApiType\ObjectType $schema, ReflectionJsonApiResource $reflection): void
    {
        if (! $meta = $reflection->getMetaType()) {
            return;
        }

        $schema
            ->addProperty('meta', $this->openApiTransformer->transform($meta))
            ->addRequired(['meta']);
    }

    private function buildRelationshipIdentifierType(Generic $relationshipType): KeyedArrayType
    {
        $reflection = ReflectionJsonApiResource::createForClass($relationshipType->name);

        return new KeyedArrayType([
            new ArrayItemType_('id', $reflection->getIdType($relationshipType)),
            new ArrayItemType_('type', $reflection->getTypeType($relationshipType)),
        ]);
    }

    /**
     * @see JsonApiResourceMethodReturnTypeExtension::getMethodReturnType()
     */
    protected function getResponseType(ObjectType $type): Type
    {
        return ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new MethodCallReferenceType($type, 'toResponse', [])
            );
    }

    public function reference(ObjectType $type): ?Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }
}
