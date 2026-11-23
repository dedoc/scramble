<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\InferExtensions\Concerns\CreatesRequestInputArrays;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\InputBag;

class RequestExtension implements MethodReturnTypeExtension
{
    use CreatesRequestInputArrays;

    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(Request::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->getName()) {
            'all', 'toArray' => $this->maybeMarkAsRequestInput(
                $event->getInstance(),
                $this->buildArrayType(),
            ),
            'input', 'post' => count($event->arguments) ? null : $this->maybeMarkAsRequestInput(
                $event->getInstance(),
                $this->buildArrayType(),
            ),
            'json' => count($event->arguments) ? null : $this->maybeMarkAsRequestInput(
                $event->getInstance(),
                new ObjectType(InputBag::class),
            ),
            'user' => new ObjectType($this->getUserModelClass()),
            default => null,
        };
    }

    protected function getUserModelClass(): string
    {
        $model = config('auth.providers.users.model');

        if ($model && is_string($model) && class_exists($model)) {
            return $model;
        }

        if (class_exists($model = \App\Models\User::class)) {
            return $model;
        }

        if (class_exists($model = \App\User::class)) {
            return $model;
        }

        return \App\Models\User::class; // @phpstan-ignore class.notFound
    }
}
