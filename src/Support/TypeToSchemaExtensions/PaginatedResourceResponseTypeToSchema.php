<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeManagers\CursorPaginatorTypeManager;
use Dedoc\Scramble\Support\TypeManagers\LengthAwarePaginatorTypeManager;
use Dedoc\Scramble\Support\TypeManagers\PaginatorTypeManager;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use LogicException;

class PaginatedResourceResponseTypeToSchema extends ResourceResponseTypeToSchema
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && $type->isInstanceOf(PaginatedResourceResponse::class)
            && count($type->templateTypes) >= 1
            && $type->templateTypes[0] instanceof Generic; // Collection
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type): Response
    {
        $resourceType = $this->getResourceType($type);

        return $this
            ->makeResponse($resourceType)
            ->setDescription($this->getPaginatedDescription($type))
            ->setContent('application/json', Schema::fromType($this->wrap(
                $this->wrapper($this->getCollectingClassType($type)),
                $this->getCollectionSchema($type),
                $this->getMergedAdditionalSchema($type),
            )));
    }

    private function getCollectionSchema(Generic $type): OpenApiType
    {
        // When transforming response's collection, we can get either a reference,
        // or an array of schema (if collection is anonymous).
        return $this->openApiTransformer->transform($this->getResourceType($type));
    }

    private function getMergedAdditionalSchema(Generic $type): OpenApiObjectType
    {
        $resourceType = $this->getResourceType($type);

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

    protected function getPaginatedInformationSchema(Generic $type): OpenApiObjectType
    {
        $resourceType = $this->getResourceType($type);

        $paginationInformation = $this->getDefaultPaginationInformationArray($type);

        if ($this->getPaginationInformationMethod($resourceType)) {
            $paginationInformation = ReferenceTypeResolver::getInstance()
                ->resolve(
                    new GlobalScope,
                    new MethodCallReferenceType($resourceType, 'paginationInformation', [
                        new UnknownType,
                        $this->getPaginatedArray($type),
                        $paginationInformation,
                    ])
                );
        }

        if (! $paginationInformation instanceof KeyedArrayType) {
            return new OpenApiObjectType;
        }

        $schema = $this->openApiTransformer->transform($paginationInformation);

        return $schema instanceof OpenApiObjectType ? $schema : new OpenApiObjectType;
    }

    protected function getPaginationInformationMethod(ObjectType $resourceType): ?FunctionLikeDefinition
    {
        $resourceTypeDefinition = $this->infer->index->getClass($resourceType->name);

        $method = $resourceTypeDefinition?->getMethod('paginationInformation');

        if (! $method) {
            return null;
        }

        return $this->isUserDefinedPaginationInformationMethod($method) ? $method : null;
    }

    protected function isUserDefinedPaginationInformationMethod(FunctionLikeDefinition $method): bool
    {
        return true;
    }

    protected function getPaginatedArray(Generic $type): KeyedArrayType
    {
        $normalizedPaginatorType = $this->getPaginatorType($type);

        $typeManager = match ($normalizedPaginatorType->name) {
            Paginator::class => new PaginatorTypeManager,
            CursorPaginator::class => new CursorPaginatorTypeManager,
            LengthAwarePaginator::class => new LengthAwarePaginatorTypeManager,
            default => null,
        };

        if (! $typeManager) {
            return new KeyedArrayType;
        }

        return $typeManager->getToArrayType(new ArrayType($normalizedPaginatorType->templateTypes[1]));
    }

    protected function getDefaultPaginationInformationArray(Generic $type): KeyedArrayType
    {
        $paginatorArray = $this->getPaginatedArray($type);

        $defaultLinkType = new Union([new StringType, new NullType]);
        $excludedMetaKeys = ['data', 'first_page_url', 'last_page_url', 'prev_page_url', 'next_page_url'];

        return new KeyedArrayType([
            new ArrayItemType_('links', new KeyedArrayType([
                new ArrayItemType_('first', $paginatorArray->getItemValueTypeByKey('first_page_url', $defaultLinkType)),
                new ArrayItemType_('last', $paginatorArray->getItemValueTypeByKey('last_page_url', $defaultLinkType)),
                new ArrayItemType_('prev', $paginatorArray->getItemValueTypeByKey('prev_page_url', $defaultLinkType)),
                new ArrayItemType_('next', $paginatorArray->getItemValueTypeByKey('next_page_url', $defaultLinkType)),
            ])),
            new ArrayItemType_(
                'meta',
                new KeyedArrayType(array_values(array_filter(
                    $paginatorArray->items,
                    fn (ArrayItemType_ $t) => ! in_array($t->key, $excludedMetaKeys),
                ))),
            ),
        ]);
    }

    protected function getPaginatedDescription(Generic $type): string
    {
        $collectedType = $this->getCollectingClassType($type);

        if ($collectedType instanceof UnknownType) {
            return 'Paginated set';
        }

        return 'Paginated set of `'.$this->openApiContext->references->schemas->uniqueName($collectedType->name).'`';
    }

    private function getCollectingClassType(Generic $type): Generic|UnknownType
    {
        return (new ResourceCollectionTypeManager($this->getResourceType($type), $this->infer->index))->getCollectedType();
    }

    private function getResourceType(Generic $type): Generic
    {
        $resourceType = $type->templateTypes[/* TData */ 0] ?? null;

        if (! $resourceType instanceof Generic) {
            throw new \InvalidArgumentException('Resource type of the response must be Generic, got '.($resourceType ? $resourceType::class : 'null'));
        }

        return $resourceType;
    }

    private function getPaginatorType(Generic $type): Generic
    {
        $paginatorType = $this->getResourceType($type)->templateTypes[/* TResource */ 0];

        if (! $paginatorType instanceof ObjectType) {
            throw new LogicException('Paginator type must be an object, got '.$paginatorType->toString());
        }

        return new Generic($paginatorType->name, [
            new IntegerType,
            $this->getCollectingClassType($type),
        ]);
    }
}
