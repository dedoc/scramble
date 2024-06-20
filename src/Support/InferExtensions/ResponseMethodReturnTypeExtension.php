<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Symfony\Component\HttpFoundation\Response;

class ResponseMethodReturnTypeExtension implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(Response::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'setContent' => tap($event->getInstance(), function (Generic $instance) use ($event) {
                $instance->templateTypes[0] = $event->getArg('content', 0);
            }),
            'setStatusCode' => tap($event->getInstance(), function (Generic $instance) use ($event) {
                $instance->templateTypes[1] = $event->getArg('code', 0);
            }),
            default => null,
        };
    }
}
