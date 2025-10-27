<?php

namespace Dedoc\Scramble\Support\Helpers;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JsonResourceHelper
{
    public static $jsonResourcesModelTypesCache = [];

    /**
     * @internal
     */
    public static function modelType(ClassDefinition $jsonClass): ?Type
    {
        if ($cachedModelType = static::$jsonResourcesModelTypesCache[$jsonClass->name] ?? null) {
            return $cachedModelType;
        }

        $modelClass = static::getModelName(
            $jsonClass->name,
            new \ReflectionClass($jsonClass->name),
            new FileNameResolver(ClassReflector::make($jsonClass->name)->getNameContext()),
        );

        $modelType = new UnknownType("Cannot resolve [$modelClass] model type.");
        if ($modelClass && is_a($modelClass, Model::class, true)) {
            $modelType = new ObjectType($modelClass);
        }

        static::$jsonResourcesModelTypesCache[$jsonClass->name] = $modelType;

        return $modelType;
    }

    private static function getModelName(string $jsonResourceClassName, \ReflectionClass $reflectionClass, FileNameResolver $getFqName)
    {
        $phpDoc = $reflectionClass->getDocComment() ?: '';

        $mixinOrPropertyLine = Str::of($phpDoc)
            ->replace(['/**', '*/'], '')
            ->explode("\n")
            ->first(fn ($str) => Str::is(['*@property*$resource', '*@mixin*'], $str));

        if ($mixinOrPropertyLine) {
            $modelName = Str::replace(['@property-read', '@property', '$resource', '@mixin', ' ', '*', "\r"], '', $mixinOrPropertyLine);

            $modelClass = $getFqName($modelName);

            if (class_exists($modelClass)) {
                return $modelClass;
            }
        }

        $modelName = (string) Str::of(Str::of($jsonResourceClassName)->explode('\\')->last())->replace('Resource', '')->singular();

        $modelClass = 'App\\Models\\'.$modelName;
        if (! class_exists($modelClass)) {
            return null;
        }

        return $modelClass;
    }
}
