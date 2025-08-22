<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Support\Arr;
use LogicException;

class PaginatedResourceResponseTypeToSchema extends ResourceResponseTypeToSchema
{

    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->isInstanceOf(PaginatedResourceResponse::class)
            && count($type->templateTypes) >= 1
            && $type->templateTypes[0] instanceof Generic; // Collection
    }

    /**
     * @param Generic $type
     */
    public function toResponse(Type $type): Response
    {
        $resourceType = $type->templateTypes[/* TData */ 0];

        // Parent `makeBaseResponse` call is needed so we can infer the modification of the `withResponse`
        // if it is defined on the resource class.
        $baseResponse = $this->openApiTransformer->toResponse(
            $this->makeBaseResponse($resourceType),
        );

        return $baseResponse
            ->setDescription($this->getPaginatedDescription($type))
            ->setContent('application/json', Schema::fromType($this->wrap(
                $type,
                $this->getCollectionSchema($type),
                $this->getMergedAdditionalSchema($type),
            )));
    }

    protected function wrap(Generic $type, OpenApiType $data, OpenApiType $additional): OpenApiType
    {
        $wrapKey = $this->wrapper($type);
        $dataIsWrapped = $this->dataIsWrapped($this->unref($data), $wrapKey);

        if ($wrapKey && !$dataIsWrapped) { // $this->haveDefaultWrapperAndDataIsUnwrapped($data)
            $data = (new OpenApiObjectType)
                ->addProperty($wrapKey, $data)
                ->setRequired([$wrapKey]);
        } elseif (! $dataIsWrapped /* && $haveAdditionalInformation */) { // $this->haveAdditionalInformationAndDataIsUnwrapped($data, $with, $additional)
            $data = (new OpenApiObjectType)
                ->addProperty($wrapKey ?? 'data', $data)
                ->setRequired([$wrapKey ?? 'data']);
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

    protected function wrapper(Generic $type): ?string
    {
        $collectedType = $this->getCollectingClassType($type);

        if (! $collectedType instanceof Generic) {
            return null;
        }

        return $collectedType->name::$wrap ?? null;
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

    protected function unref(OpenApiType $type)
    {
        if ($type instanceof Reference) {
            $type = $type->resolve();

            return $type instanceof Schema ? $type->type : throw new \Exception('Cannot unref not Schema');
        }

        return $type;
    }

    private function getCollectionSchema(Generic $type): OpenApiType
    {
        $collectionType = $this->prepareNormalizedCollectionType($type);

        // When transforming response's collection, we can get either a reference,
        // or an array of schema (if collection is anonymous).
        return $this->openApiTransformer->transform($collectionType);
    }

    private function getMergedAdditionalSchema(Generic $type): OpenApiObjectType
    {
        $resourceType = $type->templateTypes[/* TData */ 0];

        $paginationInformation = $this->getPaginatedInformationSchema($type);
        $with = $this->getWithSchema($resourceType);
        $additional = $this->getAdditionalSchema($resourceType);

        if ($with) {
            $this->mergeOpenApiObjects($paginationInformation, $with);
        }

        if ($additional) {
            $this->mergeOpenApiObjects($paginationInformation, $additional);
        }

        return $paginationInformation;
    }

    private function getWithSchema(Generic $resource): ?OpenApiObjectType
    {
        $withArray = ReferenceTypeResolver::getInstance()->resolve(
            new Infer\Scope\GlobalScope,
            new MethodCallReferenceType($resource, 'with', [])
        );

        if (! $withArray instanceof KeyedArrayType) {
            return null;
        }

        $withArray->items = $this->flattenMergeValues($withArray->items);

        return $this->openApiTransformer->transform($withArray);
    }

    private function getAdditionalSchema(Generic $resource): ?OpenApiObjectType
    {
        $additional = $resource->templateTypes[/* TAdditional */ 1] ?? null;

        if (! $additional instanceof KeyedArrayType) {
            return null;
        }

        $additional = $additional->clone();

        $additional->items = $this->flattenMergeValues($additional->items);

        return $this->openApiTransformer->transform($additional);
    }

    private function getPaginatedInformationSchema(Generic $type): OpenApiObjectType
    {
        $paginatorType = $type->templateTypes[/* TData */ 0]->templateTypes[/* TResource */ 0];

        if (! $paginatorType instanceof ObjectType) {
            throw new LogicException('Paginator type must be an object, got '.$paginatorType->toString());
        }

        $normalizedPaginatorType = new Generic($paginatorType->name, [
            new IntegerType,
            $this->getCollectingClassType($type),
        ]);

        $paginatorSchemaType = $this->openApiTransformer->transform($normalizedPaginatorType);
        if (! $paginatorSchemaType instanceof OpenApiObjectType) {
            throw new LogicException('Paginator schema is expected to be an object, got '.$paginatorSchemaType::class);
        }

        return (new OpenApiObjectType)
            ->addProperty('meta', tap(new OpenApiObjectType, function (OpenApiObjectType $type) use ($paginatorSchemaType) {
                $type->properties = Arr::except($paginatorSchemaType->properties, [
                    'data',
                    'first_page_url',
                    'last_page_url',
                    'prev_page_url',
                    'next_page_url',
                ]);
                $type->setRequired(array_keys($type->properties));
            }))
            ->addProperty('links', tap(new OpenApiObjectType, function (OpenApiObjectType $type) use ($paginatorSchemaType) {
                $defaultLinkType = (new StringType)->nullable(true);
                $type->properties = [
                    'first' => $paginatorSchemaType->properties['first_page_url'] ?? $defaultLinkType,
                    'last' => $paginatorSchemaType->properties['last_page_url'] ?? $defaultLinkType,
                    'prev' => $paginatorSchemaType->properties['prev_page_url'] ?? $defaultLinkType,
                    'next' => $paginatorSchemaType->properties['next_page_url'] ?? $defaultLinkType,
                ];
                $type->setRequired(array_keys($type->properties));
            }))
            ->setRequired(['links', 'meta']);
    }

    private function getPaginatedDescription(Generic $type): string
    {
        $collectedType = $this->getCollectingClassType($type);

        return 'Paginated set of `'.$this->openApiContext->references->schemas->uniqueName($collectedType->name).'`';
    }

    private function prepareNormalizedCollectionType(Generic $type): Generic
    {
        // The case when collection is manually annotated
        // @todo
        $collectionType = $type->templateTypes[/* TData */ 0]->clone();
        // assert generic
        $collectionType->templateTypes[0] = $this->unwrapPaginatorType($collectionType->templateTypes[0]);

        return $collectionType;
    }

    private function unwrapPaginatorType(Type $paginatorType)
    {
        return $paginatorType->templateTypes[1] ?? $paginatorType->templateTypes[0] ?? new UnknownType;
    }

    private function getCollectingClassType(Generic $type): Generic|UnknownType
    {
        return (new ResourceCollectionTypeManager($type->templateTypes[0], $this->infer->index))->getCollectedType();
    }
}
