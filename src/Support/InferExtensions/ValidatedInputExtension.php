<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\InferExtensions\Concerns\CreatesRequestInputArrays;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\ValidatedInput;

class ValidatedInputExtension implements MethodReturnTypeExtension
{
    use CreatesRequestInputArrays;

    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(ValidatedInput::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->getName()) {
            'all' => $this->maybeMarkAsRequestInput(
                $event->getInstance(),
                $this->buildArrayType(),
            ),
            default => null,
        };
    }
}
