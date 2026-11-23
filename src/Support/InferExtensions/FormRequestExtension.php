<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\InferExtensions\Concerns\CreatesRequestInputArrays;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\ValidatedInput;

class FormRequestExtension implements MethodReturnTypeExtension
{
    use CreatesRequestInputArrays;

    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(FormRequest::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->getName()) {
            'safe' => count($event->arguments) ? null : $this->maybeMarkAsRequestInput(
                $event->getInstance(),
                new ObjectType(ValidatedInput::class),
            ),
            'validated' => count($event->arguments) ? null : $this->maybeMarkAsRequestInput(
                $event->getInstance(),
                $this->buildArrayType(),
            ),
            default => null,
        };
    }
}
