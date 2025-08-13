<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Pagination\CursorPaginator;

class CursorPaginatorTypeToSchema extends TypeToSchemaExtension
{
    use WithCollectedPaginatedItems;

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
        return $type instanceof Generic
            && $type->name === CursorPaginator::class
            && $this->getCollectedType($type);
    }

    /**
     * @param  Generic  $type
     */
    public function toSchema(Type $type)
    {
        if (! $collectedType = $this->getCollectedType($type)) {
            return null;
        }

        $collectingType = $this->openApiTransformer->transform($collectedType);

        return (new OpenApiObjectType)
            ->addProperty('data', (new ArrayType)->setItems($collectingType))
            ->addProperty('path', (new StringType)->nullable(true)->setDescription('Base path for paginator generated URLs.'))
            ->addProperty('per_page', (new IntegerType)->setDescription('Number of items shown per page.'))
            ->addProperty('next_cursor', (new StringType)->nullable(true)->setDescription('The "cursor" that points to the next set of items.'))
            ->addProperty('next_page_url', (new StringType)->format('uri')->nullable(true))
            ->addProperty('prev_cursor', (new StringType)->nullable(true)->setDescription('The "cursor" that points to the previous set of items.'))
            ->addProperty('prev_page_url', (new StringType)->format('uri')->nullable(true))
            ->setRequired(['data', 'path', 'per_page', 'next_cursor', 'next_page_url', 'prev_cursor', 'prev_page_url']);
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type)
    {
        if (! $collectedType = $this->getCollectedType($type)) {
            return null;
        }

        return Response::make(200)
            ->setDescription('Paginated set of `'.$this->openApiContext->references->schemas->uniqueName($collectedType->name).'`')
            ->setContent('application/json', Schema::fromType($this->openApiTransformer->transform($type)));
    }
}
