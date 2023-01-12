<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
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
        if (! optional($scope->class())->isInstanceOf(JsonResource::class)) {
            return null;
        }

        /** $this->resource */
        if ($node instanceof Node\Expr\PropertyFetch && ($node->var->name ?? null) === 'this' && ($node->name->name ?? null) === 'resource') {
            return static::modelType($scope->class(), $scope);
        }

        /** $this->? */
        if (
            $node instanceof Node\Expr\PropertyFetch && ($node->var->name ?? null) === 'this'
            && is_string($node->name->name ?? null)
            && ($type = static::modelType($scope->class(), $scope))
        ) {
            return $type->getPropertyFetchType($node->name->name);
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
                new ObjectType(MergeValue::class),
                [
                    $node->name->name === 'merge' ? new LiteralBooleanType(true) : new BooleanType(),
                    $type,
                ],
            );
        }

        /*
         * $this->when()
         */
        if ($this->isMethodCallToThis($node, ['when'])) {
            return new Union([
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

            return new Union([
                $this->value(TypeHelper::getArgType($scope, $node->args, ['value', 1])),
                $this->value(TypeHelper::getArgType($scope, $node->args, ['default', 2], new ObjectType(MissingValue::class))),
            ]);
        }

        return null;
    }

    private static function modelType(ObjectType $jsonClass, Scope $scope): ?Type
    {
        if ($cachedModelType = static::$jsonResourcesModelTypesCache[$jsonClass->name] ?? null) {
            return $cachedModelType;
        }

        $modelClass = static::getModelName(
            $jsonClass->name,
            new \ReflectionClass($jsonClass->name),
            fn ($n) => $scope->resolveName($n)
        );

        $modelType = new UnknownType("Cannot resolve [$modelClass] model type.");
        if ($modelClass && is_a($modelClass, Model::class, true)) {
            try {
                $modelType = (new ModelInfo($modelClass))->type();
            } catch (\LogicException $e) {
                // Here doctrine/dbal is not installed.
                $modelType = null;
            }
        }

        return static::$jsonResourcesModelTypesCache[$jsonClass->name] = $modelType;
    }

    private static function getModelName(string $jsonResourceClassName, \ReflectionClass $reflectionClass, callable $getFqName)
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
