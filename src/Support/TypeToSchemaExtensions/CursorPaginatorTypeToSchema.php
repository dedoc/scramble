<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeManagers\CursorPaginatorTypeManager;
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

        $paginatorArray = (new CursorPaginatorTypeManager)->getToArrayType(new ArrayType($collectedType));

        return $this->openApiTransformer->transform($paginatorArray);
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
