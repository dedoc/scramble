<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\PropertyTypeExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Helpers\JsonResourceHelper;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;

class JsonResourceExtension implements MethodReturnTypeExtension, PropertyTypeExtension
{
    public function shouldHandle(ObjectType|string $type): bool
    {
        if (is_string($type)) {
            return is_a($type, JsonResource::class, true);
        }

        return $type->isInstanceOf(JsonResource::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'toArray' => $event->methodDefiningClassName === JsonResource::class
                ? $this->getModelMethodReturn($event->getInstance()->name, 'toArray', $event->arguments, $event->scope)
                : null,

            'response', 'toResponse' => new Generic(JsonResponse::class, [$event->getInstance(), new LiteralIntegerType(200), new ArrayType]),

            'whenLoaded' => count($event->arguments) === 1
                ? Union::wrap([
                    // Relationship type which does not really matter
                    new UnknownType('Skipped real relationship type extracting'),
                    new ObjectType(MissingValue::class),
                ])
                : Union::wrap([
                    $this->value($event->getArg('value', 1)),
                    $this->value($event->getArg('default', 2, new ObjectType(MissingValue::class))),
                ]),

            'when' => Union::wrap([
                $this->value($event->getArg('value', 1)),
                $this->value($event->getArg('default', 2, new ObjectType(MissingValue::class))),
            ]),

            'merge' => new Generic(MergeValue::class, [
                new LiteralBooleanType(true),
                $this->value($event->getArg('value', 0)),
            ]),

            'mergeWhen' => new Generic(MergeValue::class, [
                new BooleanType,
                $this->value($event->getArg('value', 1)),
            ]),

            'whenCounted' => count($event->arguments) === 1
                ? Union::wrap([new IntegerType, new ObjectType(MissingValue::class)])
                : Union::wrap([
                    $this->value($event->getArg('value', 1)),
                    $this->value($event->getArg('default', 2, new ObjectType(MissingValue::class))),
                ]),

            default => ! $event->getDefinition() || $event->getDefinition()->hasMethodDefinition($event->name)
                ? null
                : $this->proxyMethodCallToModel($event),
        };
    }

    public function getPropertyType(PropertyFetchEvent $event): ?Type
    {
        return match ($event->name) {
            'resource' => JsonResourceHelper::modelType($event->getDefinition(), $event->scope),
            default => ! $event->getDefinition() || $event->getDefinition()->hasPropertyDefinition($event->name)
                ? null
                : ReferenceTypeResolver::getInstance()->resolve(
                    $event->scope,
                    new PropertyFetchReferenceType(
                        JsonResourceHelper::modelType($event->getDefinition(), $event->scope),
                        $event->name,
                    ),
                ),
        };
    }

    private function proxyMethodCallToModel(MethodCallEvent $event)
    {
        return $this->getModelMethodReturn($event->getInstance()->name, $event->name, $event->arguments, $event->scope);
    }

    private function getModelMethodReturn(string $resourceClassName, string $methodName, array $arguments, Scope $scope)
    {
        $modelType = JsonResourceHelper::modelType($scope->index->getClassDefinition($resourceClassName), $scope);

        return ReferenceTypeResolver::getInstance()->resolve(
            $scope,
            new MethodCallReferenceType($modelType, $methodName, arguments: $arguments),
        );
    }

    private function value(Type $type)
    {
        return $type instanceof FunctionType ? $type->getReturnType() : $type;
    }
}
