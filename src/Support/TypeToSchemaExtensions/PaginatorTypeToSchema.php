<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\Paginator;

class PaginatorTypeToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->name === Paginator::class
            && count($type->templateTypes) === 1
            && $type->templateTypes[0] instanceof ObjectType;
    }

    /**
     * @param  Generic  $type
     */
    public function toSchema(Type $type)
    {
        $collectingClassType = $type->templateTypes[0];

        if (! $collectingClassType->isInstanceOf(JsonResource::class) && ! $collectingClassType->isInstanceOf(Model::class)) {
            return null;
        }

        if (! ($collectingType = $this->openApiTransformer->transform($collectingClassType))) {
            return null;
        }

        return (new OpenApiObjectType)
            ->addProperty('current_page', new IntegerType)
            ->addProperty('data', (new ArrayType)->setItems($collectingType))
            ->addProperty('first_page_url', (new StringType)->nullable(true))
            ->addProperty('from', (new IntegerType)->nullable(true))
            ->addProperty('next_page_url', (new StringType)->nullable(true))
            ->addProperty('path', (new StringType)->nullable(true)->setDescription('Base path for paginator generated URLs.'))
            ->addProperty('per_page', (new IntegerType)->setDescription('Number of items shown per page.'))
            ->addProperty('prev_page_url', (new StringType)->nullable(true))
            ->addProperty('to', (new IntegerType)->nullable(true)->setDescription('Number of the last item in the slice.'))
            ->setRequired(['current_page', 'data', 'first_page_url', 'from', 'next_page_url', 'path', 'per_page', 'prev_page_url', 'to']);
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type)
    {
        $collectingClassType = $type->templateTypes[0];

        if (! $collectingClassType->isInstanceOf(JsonResource::class) && ! $collectingClassType->isInstanceOf(Model::class)) {
            return null;
        }

        return Response::make(200)
            ->description('Paginated set of `'.$this->components->uniqueSchemaName($collectingClassType->name).'`')
            ->setContent('application/json', Schema::fromType($this->openApiTransformer->transform($type)));
    }
}
