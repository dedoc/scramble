<?php

namespace Dedoc\Scramble\Support\InferHandlers;

use Carbon\Carbon;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;

class ModelClassHandler
{
    public function shouldHandle(Node $node)
    {
        return $node instanceof Node\Stmt\Class_;
    }

    public function enter(Node $node, Scope $scope)
    {
        $type = $scope->getType($node);

        if (! $type->isInstanceOf(Model::class)) {
            return;
        }
    }

    public function leave(Node $node, Scope $scope)
    {
        $type = $scope->getType($node);

        if (! $type->isInstanceOf(Model::class)) {
            return;
        }

//        $modelType = (new ModelInfo($type->name))->type();

//        $type->properties = array_merge($modelType->properties, $type->properties);

//        dd($type);

//        if (array_key_exists('toArray', $type->methods)) {
//            return;
//        }
//
//        $type->methods['toArray'] = (new FunctionType())
//            ->setReturnType($this->getDefaultToArrayType($type, $type->name));
    }

    private function getDefaultToArrayType(ObjectType $type, string $modelName)
    {
        $modelInfo = new ModelInfo($modelName);

        /** @var Model $instance */
        $instance = app()->make($modelName);
        $info = $modelInfo->handle();

        $arrayableAttributesTypes = $info->get('attributes', collect())
            ->when($instance->getVisible(), fn ($c, $visible) => $c->only($visible))
            ->when($instance->getHidden(), fn ($c, $visible) => $c->except($visible))
            ->filter(fn ($attr) => $attr['appended'] !== false)
            ->map(function ($_, $name) use ($type) {
                $attrType = $type->getPropertyFetchType($name);
                if (
                    $attrType instanceof Union
                    && count($attrType->types) === 2
                    && $attrType->types[0] instanceof NullType
                    && $attrType->types[1]->isInstanceOf(Carbon::class)
                ) {
                    $dateStringType = new StringType();
                    $dateStringType->setAttribute('format', 'date-time');

                    return Union::wrap([new NullType(), $dateStringType]);
                }

                return $type->getPropertyFetchType($name);
            });

        $arrayableRelationsTypes = $info->get('relations', collect())
            ->when($instance->getVisible(), fn ($c, $visible) => $c->only($visible))
            ->when($instance->getHidden(), fn ($c, $visible) => $c->except($visible))
            ->map(function ($_, $name) use ($type) {
                return $type->getPropertyFetchType($name);
            });

        return new ArrayType([
            ...$arrayableAttributesTypes->map(fn ($type, $name) => new ArrayItemType_($name, $type))->values()->all(),
            ...$arrayableRelationsTypes->map(fn ($type, $name) => new ArrayItemType_($name, $type, $isOptional = true))->values()->all(),
        ]);
    }
}
