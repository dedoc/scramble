<?php

namespace Dedoc\Scramble\Support\JsonResource;

use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Support\Helpers\JsonResourceHelper;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Illuminate\Database\Eloquent\Model;

trait ResolvesModelFromJsonResourceInstance
{
    private function resolveModelFromJsonResourceInstance(ObjectType $resourceType, Index $index): ?ObjectType
    {
        $modelType = (new TypeWalker)->first(
            $resourceType,
            fn (Type $type) => $type->isInstanceOf(Model::class),
        );

        if ($modelType instanceof TemplateType) {
            $modelType = $modelType->is;
        }

        if ($modelType instanceof ObjectType) {
            return $modelType;
        }

        $classDefinition = $index->getClass($resourceType->name);

        if (! $classDefinition instanceof ClassDefinition) {
            return null;
        }

        $modelType = JsonResourceHelper::modelType($classDefinition);

        return $modelType instanceof ObjectType ? $modelType : null;
    }
}
