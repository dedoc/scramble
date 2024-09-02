<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class JsonResourceExtension implements MethodReturnTypeExtension, StaticMethodReturnTypeExtension
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
            'response', 'toResponse' => new Generic(JsonResponse::class, [$event->getInstance(), new LiteralIntegerType(200), new ArrayType]),
            default => null,
        };
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'toArray' => $this->handleToArrayStaticCall($event),
            default => null,
        };
    }

    /**
     * Note: In fact, this is not a static call to the JsonResource. This is how type inference system treats it for
     * now, when analyzing parent::toArray() call. `parent::` becomes `JsonResource::`. So this should be fixed in
     * future just for the sake of following how real code works.
     */
    private function handleToArrayStaticCall(StaticMethodCallEvent $event): ?Type
    {
        $contextClassName = $event->scope->context->classDefinition->name ?? null;

        if (! $contextClassName) {
            return null;
        }

        $modelType = JsonResourceTypeInfer::modelType($event->scope->index->getClassDefinition($contextClassName), $event->scope);

        return ReferenceTypeResolver::getInstance()->resolve(
            $event->scope,
            new MethodCallReferenceType($modelType, 'toArray', arguments: $event->arguments),
        );
    }
}
