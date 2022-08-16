<?php

namespace Dedoc\Documentor\Support\ComplexTypeHandler;

use Dedoc\Documentor\Support\Generator\Types\ArrayType;
use Dedoc\Documentor\Support\Generator\Types\BooleanType;
use Dedoc\Documentor\Support\Generator\Types\IntegerType;
use Dedoc\Documentor\Support\Generator\Types\ObjectType;
use Dedoc\Documentor\Support\Generator\Types\StringType;
use Dedoc\Documentor\Support\Generator\Types\Type;
use Dedoc\Documentor\Support\Type\Generic;
use Dedoc\Documentor\Support\Type\Identifier;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class LengthAwarePaginatorHandler
{
    private Generic $type;

    public function __construct(Generic $type)
    {
        $this->type = $type;
    }

    public static function shouldHandle(\Dedoc\Documentor\Support\Type\Type $type)
    {
        return $type instanceof Generic
            && $type->type->name === LengthAwarePaginator::class
            && count($type->genericTypes) === 1
            && $type->genericTypes[0] instanceof Identifier;
    }


    public function handle(): ?Type
    {

        /** @var Identifier $collectingClassType */
        $collectingClassType = $this->type->genericTypes[0];

        if (! is_a($collectingClassType->name, JsonResource::class, true)) {
            return null;
        }

        if (! ($collectingType = ComplexTypeHandlers::handle($collectingClassType))) {
            return null;
        }

        $type = new ObjectType;
        $type->addProperty('data', (new ArrayType())->setItems($collectingType));
        $type->addProperty(
            'links',
            (new ObjectType())
                ->addProperty('first', (new StringType)->nullable(true))
                ->addProperty('last', (new StringType)->nullable(true))
                ->addProperty('prev', (new StringType)->nullable(true))
                ->addProperty('next', (new StringType)->nullable(true))
                ->setRequired(['first', 'last', 'prev', 'next'])
        );
        $type->addProperty(
            'meta',
            (new ObjectType())
                ->addProperty('current_page', new IntegerType)
                ->addProperty('from', (new IntegerType)->nullable(true))
                ->addProperty('last_page', new IntegerType)
                ->addProperty('links', (new ArrayType)->setItems(
                    (new ObjectType)
                        ->addProperty('url', (new StringType)->nullable(true))
                        ->addProperty('label', new StringType)
                        ->addProperty('active', new BooleanType)
                        ->setRequired(['url', 'label', 'active'])
                )->setDescription('Generated paginator links.'))
                ->addProperty('path', (new StringType)->nullable(true)->setDescription('Base path for paginator generated URLs.'))
                ->addProperty('per_page', (new IntegerType)->setDescription('Number of items shown per page.'))
                ->addProperty('to', (new IntegerType)->nullable(true)->setDescription('Number of the last item in the slice.'))
                ->addProperty('total', (new IntegerType)->setDescription('Total number of items being paginated.'))
                ->setRequired(['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'])
        );
        $type->setRequired(['data', 'links', 'meta']);

        return $type->setDescription('Paginated set of `'.ComplexTypeHandlers::$components->uniqueSchemaName($collectingClassType->name).'`');
    }
}
