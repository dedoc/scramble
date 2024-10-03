<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;

class CursorPaginatorTypeToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->name === CursorPaginator::class
            && count($type->templateTypes) === 1
            && $type->templateTypes[0] instanceof ObjectType;
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

        if (! ($collectingType = $this->openApiTransformer->transform($collectingClassType))) {
            return null;
        }

        $type = new OpenApiObjectType;
        $type->addProperty('data', (new ArrayType)->setItems($collectingType));
        $type->addProperty(
            'links',
            (new OpenApiObjectType)
                ->addProperty('first', (new StringType)->nullable(true))
                ->addProperty('last', (new StringType)->nullable(true))
                ->addProperty('prev', (new StringType)->nullable(true))
                ->addProperty('next', (new StringType)->nullable(true))
                ->setRequired(['first', 'last', 'prev', 'next'])
        );
        $type->addProperty(
            'meta',
            (new OpenApiObjectType)
                ->addProperty('path', (new StringType)->nullable(true)->setDescription('Base path for paginator generated URLs.'))
                ->addProperty('per_page', (new IntegerType)->setDescription('Number of items shown per page.'))
                ->addProperty('next_cursor', (new StringType)->nullable(true))
                ->addProperty('prev_cursor', (new StringType)->nullable(true))
                ->setRequired(['path', 'per_page', 'next_cursor', 'prev_cursor'])
        );
        $type->setRequired(['data', 'links', 'meta']);

        return Response::make(200)
            ->description('Paginated set of `'.$this->components->uniqueSchemaName($collectingClassType->name).'`')
            ->setContent('application/json', Schema::fromType($type));
    }
}
