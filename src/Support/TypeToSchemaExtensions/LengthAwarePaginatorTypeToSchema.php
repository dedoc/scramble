<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Reference;
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
use Illuminate\Pagination\LengthAwarePaginator;

class LengthAwarePaginatorTypeToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->name === LengthAwarePaginator::class
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
        $type->addProperty('data', (new ArrayType())->setItems($collectingType));


        $links = (new OpenApiObjectType)
            ->addProperty('first', (new StringType)->nullable(true))
            ->addProperty('last', (new StringType)->nullable(true))
            ->addProperty('prev', (new StringType)->nullable(true))
            ->addProperty('next', (new StringType)->nullable(true))
            ->setRequired(['first', 'last', 'prev', 'next']);

        $linksSchema = Schema::fromType($links);
        $linksSchemaName = 'PaginatedSetLinks';
        if(!$this->components->hasSchema($linksSchemaName)) {
            $this->components->addSchema($linksSchemaName, $linksSchema);
        }

        $type->addProperty(
            'links',
            new Reference('schemas', $linksSchemaName, $this->components)
        );

        $meta = (new OpenApiObjectType)
            ->addProperty('current_page', new IntegerType)
            ->addProperty('from', (new IntegerType)->nullable(true))
            ->addProperty('last_page', new IntegerType)
            ->addProperty('links', (new ArrayType)->setItems(
                (new OpenApiObjectType)
                    ->addProperty('url', (new StringType)->nullable(true))
                    ->addProperty('label', new StringType)
                    ->addProperty('active', new BooleanType)
                    ->setRequired(['url', 'label', 'active'])
            )->setDescription('Generated paginator links.'))
            ->addProperty('path', (new StringType)->nullable(true)->setDescription('Base path for paginator generated URLs.'))
            ->addProperty('per_page', (new IntegerType)->setDescription('Number of items shown per page.'))
            ->addProperty('to', (new IntegerType)->nullable(true)->setDescription('Number of the last item in the slice.'))
            ->addProperty('total', (new IntegerType)->setDescription('Total number of items being paginated.'))
            ->setRequired(['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total']);

        $metaSchema = Schema::fromType($meta);
        $metaSchemaName = 'PaginatedSetMeta';
        if(!$this->components->hasSchema($metaSchemaName)) {
            $this->components->addSchema($metaSchemaName, $metaSchema);
        }

        $type->addProperty(
            'meta',
            new Reference('schemas', $metaSchemaName, $this->components)
        );
        $type->setRequired(['data', 'links', 'meta']);

        return Response::make(200)
            ->description('Paginated set of `'.$this->components->uniqueSchemaName($collectingClassType->name).'`')
            ->setContent('application/json', Schema::fromType($type));
    }
}
