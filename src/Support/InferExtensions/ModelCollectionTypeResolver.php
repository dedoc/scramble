<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class ModelCollectionTypeResolver
{
    public static function resolve(ObjectType $modelType): Type
    {
        return ReferenceTypeResolver::getInstance()->resolve(
            new GlobalScope,
            new NewCallReferenceType(
                static::resolveClass($modelType->name),
                [new ArrayType($modelType)],
            ),
        );
    }

    public static function resolveClass(string $modelClass): string
    {
        try {
            $reflectionMethod = new \ReflectionMethod($modelClass, 'newCollection');

            if ($reflectionMethod->getDeclaringClass()->getName() === Model::class) {
                return EloquentCollection::class;
            }

            /** @var Model $model */
            $model = app($modelClass);

            return get_class($model->newCollection([]));
        } catch (Throwable) {
            return EloquentCollection::class;
        }
    }
}
