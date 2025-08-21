<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\ResourceResponse;

class JsonResourceTypeToSchema extends TypeToSchemaExtension
{
    use FlattensMergeValues;
    use MergesOpenApiObjects;

    public function __construct(
        Infer $infer,
        TypeTransformer $openApiTransformer,
        Components $components,
        protected OpenApiContext $openApiContext
    ) {
        parent::__construct($infer, $openApiTransformer, $components);
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
        $array = ReferenceTypeResolver::getInstance()->resolve(
            new GlobalScope,
            (new MethodCallReferenceType($type, 'toArray', arguments: []))
        );

        // @todo: why unpacking is here? ReferenceTypeResolver@resolve should've returned unpacked type
        $array = TypeHelper::unpackIfArray($array);

        // The case when `toArray` is not defined.
        if ($array instanceof ArrayType) {
            return $this->openApiTransformer->transform($array);
        }

        if (! $array instanceof KeyedArrayType) {
            return new UnknownType;
        }

        $array->items = $this->flattenMergeValues($array->items);
        $array->isList = KeyedArrayType::checkIsList($array->items);

        return $this->openApiTransformer->transform($array);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toResponse(Type $type)
    {
        $resourceResponseType = new Generic(ResourceResponse::class, [$type]);

        return (new ResourceResponseTypeToSchema($this->infer, $this->openApiTransformer, $this->components, $this->openApiContext))
            ->toResponse($resourceResponseType);
    }

    public function reference(ObjectType $type)
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);

        /*
         * @todo: Allow (enforce) user to explicitly pass short and unique names for the reference and avoid passing components.
         * Otherwise, only class names are correctly handled for now.
         */
        return Reference::in('schemas')
            ->shortName(class_basename($type->name))
            ->uniqueName($type->name);
    }
}
