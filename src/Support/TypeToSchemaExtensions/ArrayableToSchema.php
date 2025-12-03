<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiSchema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class ArrayableToSchema extends TypeToSchemaExtension
{
    public function __construct(
        Infer $infer,
        TypeTransformer $openApiTransformer,
        Components $components,
        protected OpenApiContext $openApiContext
    ) {
        parent::__construct($infer, $openApiTransformer, $components);
    }

    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(Arrayable::class)
            && ! $type->isInstanceOf(Collection::class) // prevents collections being documented in schemas
            && ((new \ReflectionClass($type->name))->isInstantiable()); // @phpstan-ignore argument.type
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type): OpenApiSchema
    {
        $this->infer->analyzeClass($type->name);

        $toArrayReturnType = $type->getMethodReturnType('toArray');

        return $this->openApiTransformer->transform($toArrayReturnType);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toResponse(Type $type): ?Response
    {
        return Response::make(200)
            ->setDescription('`'.$this->openApiContext->references->schemas->uniqueName($type->name).'`')
            ->setContent(
                'application/json',
                Schema::fromType($this->openApiTransformer->transform($type)),
            );
    }

    public function reference(ObjectType $type): Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }
}
