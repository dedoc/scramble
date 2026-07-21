<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\JsonResource\JsonResourceVariantMatcher;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\ResourceResponse;

class JsonResourceTypeToSchema extends TypeToSchemaExtension
{
    use FlattensMergeValues;
    use MergesOpenApiObjects;

    private JsonResourceVariantMatcher $variantMatcher;

    public function __construct(
        Infer $infer,
        TypeTransformer $openApiTransformer,
        Components $components,
        protected OpenApiContext $openApiContext,
    ) {
        parent::__construct($infer, $openApiTransformer, $components);

        $this->variantMatcher = new JsonResourceVariantMatcher($infer->index);
    }

    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(JsonResource::class)
            && ! $type->isInstanceOf(ResourceCollection::class);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type)
    {
        $type = $this->normalizeType($type);

        $array = ReferenceTypeResolver::getInstance()->resolve(
            new GlobalScope,
            new MethodCallReferenceType($type, 'toArray', arguments: []),
        );

        if (! $array instanceof KeyedArrayType) {
            return $this->openApiTransformer->getOrCreateSchemaReference(
                $this->defaultReference($type),
                fn () => $this->openApiTransformer->transform($array),
            );
        }

        $variant = $this->variantMatcher->match($type);

        $reference = $this->openApiTransformer->getOrCreateSchemaReference(
            $variant->isAnonymous() ? $this->defaultReference($type) : $variant->reference($this->components),
            fn () => $this->openApiTransformer->transform($this->flatten($variant->filterReferencableFields($array))),
        );

        $loadedFields = $variant->filterLoadedFields($array);

        return $this->allOf([
            $reference,
            count($loadedFields->items) ? $this->openApiTransformer->transform($this->flatten($loadedFields)) : null,
        ]);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toResponse(Type $type)
    {
        return $this->openApiTransformer->toResponse(
            $this->getResponseType($type)
        );
    }

    protected function normalizeType(ObjectType $type): Generic
    {
        return $type instanceof Generic ? $type : new Generic($type->name, [new \Dedoc\Scramble\Support\Type\UnknownType]);
    }

    protected function getResponseType(ObjectType $type): Type
    {
        return new Generic(ResourceResponse::class, [$type]);
    }

    protected function defaultReference(ObjectType $type): ?Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }

    private function flatten(KeyedArrayType $type): KeyedArrayType
    {
        $type->items = $this->flattenMergeValues($type->items);
        $type->isList = KeyedArrayType::checkIsList($type->items);

        return $type;
    }

    /**
     * @param  list<OpenApiType|null>  $jsonSchemas
     */
    private function allOf(array $jsonSchemas): OpenApiType
    {
        $jsonSchemas = array_values(array_filter($jsonSchemas));

        if (count($jsonSchemas) === 1) {
            return $jsonSchemas[0];
        }

        return (new AllOf)->setItems($jsonSchemas);
    }
}
