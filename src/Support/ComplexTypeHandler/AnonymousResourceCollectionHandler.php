<?php

namespace Dedoc\Scramble\Support\ComplexTypeHandler;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Identifier;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class AnonymousResourceCollectionHandler
{
    private Generic $type;

    public function __construct(Generic $type)
    {
        $this->type = $type;
    }

    public static function shouldHandle(\Dedoc\Scramble\Support\Type\Type $type)
    {
        return $type instanceof Generic
            && $type->type->name === AnonymousResourceCollection::class
            && count($type->genericTypes) === 1;
    }

    public function handle(): ?Type
    {
        /** @var Identifier $collectingClassType */
        $collectingClassType = $this->type->genericTypes[0];

        // This is primarily for the case when collected class is a paginator.
        if ($collectingClassType instanceof Generic) {
            return ComplexTypeHandlers::handle($collectingClassType);
        }

        if (! $collectingClassType instanceof Identifier) {
            return null;
        }

        if (! is_a($collectingClassType->name, JsonResource::class, true)) {
            return null;
        }

        $responseWrapKey = ($collectingClassType->name)::$wrap;

        if (! ($type = ComplexTypeHandlers::handle($collectingClassType))) {
            return null;
        }

        $type = $responseWrapKey
            ? (new ObjectType)->addProperty($responseWrapKey, $type)->setRequired([$responseWrapKey])
            : (new ArrayType())->setItems($type);

        return $type->setHint('Array of `'.ComplexTypeHandlers::$components->uniqueSchemaName($collectingClassType->name).'`');
    }
}
