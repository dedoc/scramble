<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Support\Str;
use PhpParser\Node;

class JsonResourceTypeInfer
{
    public static $jsonResourcesModelTypesCache = [];

    public function getNodeReturnType(Node $node, Scope $scope)
    {
        if (! optional($scope->class())->isInstanceOf(JsonResource::class)) {
            return null;
        }

        /** $this->resource */
        if ($node instanceof Node\Expr\PropertyFetch && ($node->var->name ?? null) === 'this' && ($node->name->name ?? null) === 'resource') {
            return static::modelType($scope->class(), $scope);
        }

        /** $this->? */
        if ($node instanceof Node\Expr\PropertyFetch && ($node->var->name ?? null) === 'this' && is_string($node->name->name ?? null)) {
            return static::modelType($scope->class(), $scope)->getPropertyFetchType($node->name->name);
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
            $type = $scope->getType($node->args[count($node->args) - 1]->value);

            if ($type instanceof FunctionType) {
                $type = $type->getReturnType();
            }

            return $type;
        }
        if ($node instanceof Node\Expr\ArrayItem && $this->isMethodCallToThis($node->value, ['when'])) {
            $scope->getType($node)->isOptional = true;

            return null;
        }

        /*
         * new JsonResource($this->whenLoaded)
         */
        if (
            $node instanceof Node\Expr\ArrayItem
            && $node->value instanceof Node\Expr\New_
            && $scope->getType($node->value)->isInstanceOf(JsonResource::class)
            && $this->isMethodCallToThis(optional($node->value->args[0])->value, ['whenLoaded'])
        ) {
            $scope->getType($node)->isOptional = true;

            return null;
        }
    }

    private static function modelType(ObjectType $jsonClass, Scope $scope): Type
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
            $modelType = (new ModelInfo($modelClass))->type();
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
}
