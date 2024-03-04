<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr;

class JsonResourceTypeInfer implements ExpressionTypeInferExtension
{
    public static $jsonResourcesModelTypesCache = [];

    public function getType(Expr $node, Scope $scope): ?Type
    {
        if (
            ! $scope->classDefinition()?->isInstanceOf(JsonResource::class)
            || $scope->classDefinition()?->name === JsonResource::class
        ) {
            return null;
        }

        /** $this->resource */
        if ($node instanceof Node\Expr\PropertyFetch && ($node->var->name ?? null) === 'this' && ($node->name->name ?? null) === 'resource') {
            return static::modelType($scope->classDefinition(), $scope);
        }

        /** $this->? */
        if (
            $node instanceof Node\Expr\PropertyFetch && ($node->var->name ?? null) === 'this'
            && is_string($node->name->name ?? null)
            && ! array_key_exists($node->name->name, $scope->classDefinition()->properties)
            && ($type = static::modelType($scope->classDefinition(), $scope))
        ) {
            return $scope->getPropertyFetchType($type, $node->name->name);
        }

        /*
         * $this->merge()
         * $this->mergeWhen()
         */
        if ($this->isMethodCallToThis($node, ['merge', 'mergeWhen'])) {
            $type = $scope->getType($node->args[count($node->args) - 1]->value);

            if ($type instanceof FunctionType) {
                $type = $type->getReturnType();
            }

            return new Generic(
                MergeValue::class,
                [
                    $node->name->name === 'merge' ? new LiteralBooleanType(true) : new BooleanType(),
                    $type,
                ],
            );
        }

        /*
         * new MissingValue
         */
        if ($scope->getType($node)->isInstanceOf(MissingValue::class)) {
            return new ObjectType(MissingValue::class);
        }

        /*
         * $this->when()
         */
        if ($this->isMethodCallToThis($node, ['when'])) {
            return Union::wrap([
                $this->value(TypeHelper::getArgType($scope, $node->args, ['value', 1])),
                $this->value(TypeHelper::getArgType($scope, $node->args, ['default', 2], new ObjectType(MissingValue::class))),
            ]);
        }

        /*
         * $this->whenLoaded()
         */
        if ($this->isMethodCallToThis($node, ['whenLoaded'])) {
            if (count($node->args) === 1) {
                return new Union([
                    // Relationship type which does not really matter
                    new UnknownType('Skipped real relationship type extracting'),
                    new ObjectType(MissingValue::class),
                ]);
            }

            return Union::wrap([
                $this->value(TypeHelper::getArgType($scope, $node->args, ['value', 1])),
                $this->value(TypeHelper::getArgType($scope, $node->args, ['default', 2], new ObjectType(MissingValue::class))),
            ]);
        }

        /*
         * $this->whenCounted()
         */
        if ($this->isMethodCallToThis($node, ['whenCounted'])) {
            if (count($node->args) === 1) {
                return new Union([
                    new IntegerType(),
                    new ObjectType(MissingValue::class),
                ]);
            }

            return Union::wrap([
                $this->value(TypeHelper::getArgType($scope, $node->args, ['value', 1])),
                $this->value(TypeHelper::getArgType($scope, $node->args, ['default', 2], new ObjectType(MissingValue::class))),
            ]);
        }

        return null;
    }

    private static function modelType(ClassDefinition $jsonClass, Scope $scope): ?Type
    {
        if ([$cachedModelType, $cachedModelDefinition] = static::$jsonResourcesModelTypesCache[$jsonClass->name] ?? null) {
            if ($cachedModelDefinition) {
                $scope->index->registerClassDefinition($cachedModelDefinition);
            }

            return $cachedModelType;
        }

        $modelClass = static::getModelName(
            $jsonClass->name,
            new \ReflectionClass($jsonClass->name),
            $scope->nameResolver,
        );

        $modelType = new UnknownType("Cannot resolve [$modelClass] model type.");
        $modelClassDefinition = null;
        if ($modelClass && is_a($modelClass, Model::class, true)) {
            try {
                $modelClassDefinition = (new ModelInfo($modelClass))->type();

                $scope->index->registerClassDefinition($modelClassDefinition);

                $modelType = new ObjectType($modelClassDefinition->name);
            } catch (\LogicException $e) {
                // Here doctrine/dbal is not installed.
                $modelType = null;
                $modelClassDefinition = null;
            }
        }

        static::$jsonResourcesModelTypesCache[$jsonClass->name] = [$modelType, $modelClassDefinition];

        return $modelType;
    }

    private static function getModelName(string $jsonResourceClassName, \ReflectionClass $reflectionClass, FileNameResolver $getFqName)
    {
        $phpDoc = $reflectionClass->getDocComment() ?: '';

        $mixinOrPropertyLine = Str::of($phpDoc)
            ->explode("\n")
            ->first(fn ($str) => Str::is(['*@property*$resource', '*@mixin*'], $str));

        if ($mixinOrPropertyLine) {
            $modelName = Str::replace(['@property', '$resource', '@mixin', ' ', '*'], '', $mixinOrPropertyLine);

            $modelClass = $getFqName($modelName);

            if (class_exists($modelClass)) {
                return '\\'.$modelClass;
            }
        }

        $modelName = (string) Str::of(Str::of($jsonResourceClassName)->explode('\\')->last())->replace('Resource', '')->singular();

        $modelClass = 'App\\Models\\'.$modelName;
        if (! class_exists($modelClass)) {
            return null;
        }

        return $modelClass;
    }

    private function isMethodCallToThis(?Node $node, array $methods)
    {
        if (! $node) {
            return false;
        }

        if (! $node instanceof Node\Expr\MethodCall) {
            return false;
        }

        if (($node->var->name ?? null) !== 'this') {
            return false;
        }

        return in_array($node->name->name ?? null, $methods);
    }

    private function value(Type $type)
    {
        return $type instanceof FunctionType ? $type->getReturnType() : $type;
    }
}
