<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Analyzer\MethodQuery;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\ResourceResponse;
use LogicException;

class ResourceResponseTypeToSchema extends TypeToSchemaExtension
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

    /**
     * @phpstan-assert-if-true Generic $type
     */
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && $type->isInstanceOf(ResourceResponse::class)
            && count($type->templateTypes) >= 1;
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type): Response
    {
        $resource = $type->templateTypes[0];

        if (! $resource instanceof ObjectType) {
            throw new LogicException('ResourceResponse data is expected to be an object');
        }

        return $this
            ->makeResponse($resource)
            ->setDescription($this->getDescription($resource))
            ->setContent('application/json', Schema::fromType($this->wrap(
                $this->wrapper($resource),
                $this->openApiTransformer->transform($resource),
                $this->getMergedAdditionalSchema($resource),
            )));
    }

    protected function getDescription(ObjectType $resourceType): string
    {
        if ($this->isNonReferencedResourceCollection($resourceType)) {
            return $this->getNonReferencedResourceCollectionDescription($resourceType);
        }

        return '`'.$this->openApiContext->references->schemas->uniqueName($resourceType->name).'`';
    }

    private function getMergedAdditionalSchema(ObjectType $resourceType): ?OpenApiType
    {
        $with = $this->getWithSchema($resourceType);
        $additional = $this->getAdditionalSchema($resourceType);

        if (! $with && ! $additional) {
            return null;
        }

        $mergedAdditional = new OpenApiObjectType;

        if ($with) {
            $this->mergeOpenApiObjects($mergedAdditional, $with);
        }

        if ($additional) {
            $this->mergeOpenApiObjects($mergedAdditional, $additional);
        }

        return $mergedAdditional;
    }

    protected function getNonReferencedResourceCollectionDescription(ObjectType $resourceType): string
    {
        $resourceType = (! $resourceType instanceof Generic) ? new Generic($resourceType->name) : $resourceType;

        $collectedResourceType = (new ResourceCollectionTypeManager($resourceType, $this->infer->index))->getCollectedType();

        if (! $collectedResourceType instanceof ObjectType) {
            return 'Array of items';
        }

        return 'Array of `'.$this->openApiContext->references->schemas->uniqueName($collectedResourceType->name).'`';
    }

    protected function isNonReferencedResourceCollection(ObjectType $resourceType): bool
    {
        if (! $resourceType->isInstanceOf(ResourceCollection::class)) {
            return false;
        }

        return ! $this->getResourceCollectionTypeToSchemaInstance()->shouldReferenceResourceCollection($resourceType);
    }

    protected function getResourceCollectionTypeToSchemaInstance(): ResourceCollectionTypeToSchema
    {
        return new ResourceCollectionTypeToSchema(
            $this->infer,
            $this->openApiTransformer,
            $this->components,
            $this->openApiContext,
        );
    }

    protected function makeResponse(ObjectType $resourceType): Response
    {
        $baseResponse = $this->openApiTransformer->toResponse(
            $this->makeBaseResponseType($resourceType),
        );

        if (! $baseResponse instanceof Response) {
            throw new LogicException('Paginated base response is expected to be an instance of Response, got '.($baseResponse ? $baseResponse::class : 'null'));
        }

        return $baseResponse;
    }

    protected function makeBaseResponseType(ObjectType $resourceType): Generic
    {
        $definition = $this->infer->analyzeClass($resourceType->name);

        $responseType = new Generic(JsonResponse::class, [new UnknownType, new LiteralIntegerType(200), new KeyedArrayType]);

        $methodQuery = MethodQuery::make($this->infer)
            ->withArgumentType([null, 1], $responseType)
            ->from($definition, 'withResponse');

        $effectTypes = $methodQuery->getTypes(fn ($t) => (bool) (new TypeWalker)->first($t, fn ($t) => $t === $responseType));

        $effectTypes
            ->filter(fn ($t) => $t instanceof AbstractReferenceType)
            ->each(function (AbstractReferenceType $t) use ($methodQuery) {
                ReferenceTypeResolver::getInstance()->resolve($methodQuery->getScope(), $t);
            });

        return $responseType;
    }

    protected function wrap(?string $wrapKey, OpenApiType $data, ?OpenApiType $additional): OpenApiType
    {
        $dataIsWrapped = $this->dataIsWrapped($this->unref($data), $wrapKey);

        if ($wrapKey && ! $dataIsWrapped) { // $this->haveDefaultWrapperAndDataIsUnwrapped($data)
            $data = (new OpenApiObjectType)
                ->addProperty($wrapKey, $data)
                ->setRequired([$wrapKey]);
        } elseif (! $dataIsWrapped && $additional) { // $this->haveAdditionalInformationAndDataIsUnwrapped($data, $with, $additional)
            $data = (new OpenApiObjectType)
                ->addProperty($wrapKey ?? 'data', $data)
                ->setRequired([$wrapKey ?? 'data']);
        }

        if (! $additional) {
            return $data;
        }

        if ($data instanceof OpenApiObjectType) {
            $this->mergeOpenApiObjects($data, $additional);

            return $data;
        }

        return (new AllOf)->setItems([
            $data,
            $additional,
        ]);
    }

    protected function wrapper(Type $resource): ?string
    {
        if (! $resource instanceof ObjectType) {
            return null;
        }

        return $resource->name::$wrap ?? null;
    }

    protected function dataIsWrapped(OpenApiType $unrefedData, ?string $wrapKey): bool
    {
        if (! $wrapKey) {
            return false;
        }

        if (! $unrefedData instanceof OpenApiObjectType) {
            return false;
        }

        return array_key_exists($wrapKey, $unrefedData->properties);
    }

    protected function unref(OpenApiType $type): OpenApiType
    {
        if ($type instanceof Reference) {
            $type = $type->resolve();

            return $type instanceof Schema ? $type->type : throw new \Exception('Cannot unref not Schema');
        }

        return $type;
    }

    protected function getWithSchema(ObjectType $resource): ?OpenApiObjectType
    {
        if (! $withArray = $this->getWithType($resource)) {
            return null;
        }

        $withArray = $withArray->clone();

        $withArray->items = $this->flattenMergeValues($withArray->items);

        $schema = $this->openApiTransformer->transform($withArray);

        if (! $schema instanceof OpenApiObjectType) {
            return null;
        }

        return $schema;
    }

    protected function getWithType(ObjectType $type): ?KeyedArrayType
    {
        $withArray = ReferenceTypeResolver::getInstance()->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType($type, 'with', [])
        );

        if (! $withArray instanceof KeyedArrayType) {
            return null;
        }

        return $withArray;
    }

    protected function getAdditionalSchema(ObjectType $resource): ?OpenApiObjectType
    {
        if (! $resource instanceof Generic) {
            return null;
        }

        $additional = $resource->templateTypes[/* TAdditional */ 1] ?? null;

        if (! $additional instanceof KeyedArrayType) {
            return null;
        }

        $additional = $additional->clone();

        $additional->items = $this->flattenMergeValues($additional->items);

        $schema = $this->openApiTransformer->transform($additional);

        if (! $schema instanceof OpenApiObjectType) {
            return null;
        }

        return $schema;
    }
}
