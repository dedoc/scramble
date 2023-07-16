<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\Eloquent\Model;

class ModelToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(Model::class);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type)
    {
        $this->infer->analyzeClass($type->name);

        $toArrayReturnType = $type->getMethodReturnType('toArray');

        return $this->openApiTransformer->transform($toArrayReturnType);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toResponse(Type $type)
    {
        return Response::make(200)
            ->description('`'.$this->components->uniqueSchemaName($type->name).'`')
            ->setContent(
                'application/json',
                Schema::fromType($this->openApiTransformer->transform($type)),
            );
    }

    public function reference(ObjectType $type)
    {
        return new Reference('schemas', $type->name, $this->components);
    }
}
