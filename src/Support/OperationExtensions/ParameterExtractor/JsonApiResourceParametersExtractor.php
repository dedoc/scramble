<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Reflection\ReflectionJsonApiResource;
use Dedoc\Scramble\Support\Factories\JsonApiQueryParameterFactory;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class JsonApiResourceParametersExtractor implements ParameterExtractor
{
    public function __construct(
        private TypeTransformer $openApiTransformer,
        private JsonApiQueryParameterFactory $queryParameterFactory,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        if (! $returnType = $routeInfo->getActionType()?->getReturnType()) {
            return $parameterExtractionResults;
        }

        if (! $resourceType = $this->getResourceType($returnType)) {
            return $parameterExtractionResults;
        }

        $reflectionJsonApi = ReflectionJsonApiResource::createForClass($resourceType->name);

        $parameters = array_values(array_filter([
            $this->getIncludeParameter($reflectionJsonApi),
            ...$this->getAllIncludedSparseFieldsParameters($reflectionJsonApi),
        ]));

        if (! $parameters) {
            return $parameterExtractionResults;
        }

        return [
            ...$parameterExtractionResults,
            new ParametersExtractionResult($parameters),
        ];
    }

    private function getIncludeParameter(ReflectionJsonApiResource $reflectionJsonApi): ?Parameter
    {
        $includes = $this->getAvailableRelations($reflectionJsonApi);

        if (! $includes) {
            return null;
        }

        return $this->queryParameterFactory->createEnumArray(
            name: 'include',
            values: $includes,
        );
    }

    /**
     * @return list<Parameter|null>
     */
    private function getAllIncludedSparseFieldsParameters(ReflectionJsonApiResource $reflectionJsonApi): array
    {
        return [
            $this->getSparseFieldsParameter($reflectionJsonApi),
            ...array_map(
                fn ($rn) => $this->getSparseFieldsParameter(ReflectionJsonApiResource::createForClass($rn)),
                $this->getAvailableIncludeResourcesNames($reflectionJsonApi)
            ),
        ];
    }

    private function getSparseFieldsParameter(ReflectionJsonApiResource $reflectionJsonApi): ?Parameter
    {
        if (! $fields = $this->getAvailableFields($reflectionJsonApi)) {
            return null;
        }

        if (! $type = $this->getType($reflectionJsonApi)) {
            return null;
        }

        return $this->queryParameterFactory->createEnumArray(
            name: 'fields['.$type.']',
            values: $fields,
        );
    }

    /**
     * @todo Union
     */
    private function getResourceType(Type $type): ?ObjectType
    {
        if ($type instanceof TemplateType) {
            $type = $type->is;
        }

        if (! $type instanceof ObjectType) {
            return null;
        }

        if ($type instanceof Generic && $type->isInstanceOf(AnonymousResourceCollection::class)) {
            if (count($type->templateTypes) < 3) {
                return null;
            }

            $type = $type->templateTypes[2 /* TCollects */];
        }

        if ($type->isInstanceOf(JsonApiResource::class)) {
            return $type;
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function getAvailableRelations(ReflectionJsonApiResource $reflectionJsonApi): array
    {
        if (! $relationshipsType = $reflectionJsonApi->getRelationshipsType()) {
            return [];
        }

        $includes = [];
        foreach ($relationshipsType->items as $item) {
            if ($item->isNumericKey()) {
                continue;
            }
            $includes[] = $item->key;
        }

        return $includes;
    }

    /**
     * @return string[]
     */
    private function getAvailableIncludeResourcesNames(ReflectionJsonApiResource $reflectionJsonApi): array
    {
        if (! $relationships = $reflectionJsonApi->getRelationshipsType()) {
            return [];
        }

        $includesNames = [];
        foreach ($relationships->items as $index => $item) {
            if ($item->value->isInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class)) {
                $includeType = $item->value->templateTypes[2 /* TResource */]; // @todo possible error here
            } elseif ($item->value->isInstanceOf(JsonApiResource::class)) {
                $includeType = $item->value;
            } else {
                unset($relationships->items[$index]);

                continue;
            }
            $includeType = $includeType instanceof TemplateType ? $includeType->is : $includeType;
            if (! $includeType->isInstanceOf(JsonApiResource::class)) {
                continue;
            }
            $includesNames[] = $includeType->name;
        }

        return $includesNames;
    }

    /**
     * @return string[]
     */
    private function getAvailableFields(ReflectionJsonApiResource $reflectionJsonApi): array
    {
        if (! $attributesType = $reflectionJsonApi->getAttributesType()) {
            return [];
        }

        $fields = [];
        foreach ($attributesType->items as $item) {
            if ($item->isNumericKey()) {
                continue;
            }
            $fields[] = $item->key;
        }

        return $fields;
    }

    private function getType(ReflectionJsonApiResource $reflectionJsonApi): ?string
    {
        $type = $reflectionJsonApi->getTypeType(new Generic($reflectionJsonApi->name, []));

        return $type instanceof LiteralString ? $type->getValue() : null;
    }
}
