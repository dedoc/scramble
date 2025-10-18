<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AnyMethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Extensions\Event\AnyMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class PaginateMethodsReturnTypeExtension implements AnyMethodReturnTypeExtension, StaticMethodReturnTypeExtension
{
    const PAGINATE_METHODS = [
        'paginate',
        'simplePaginate',
        'cursorPaginate',
        'fastPaginate',
        'simpleFastPaginate',
    ];

    public function shouldHandle(string $name): bool
    {
        return $this->isQueryLikeClass($name);
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        if (! in_array($event->name, PaginateMethodsReturnTypeExtension::PAGINATE_METHODS)) {
            return null;
        }

        return $this->getPaginatorType($event->name);
    }

    public function getMethodReturnType(AnyMethodCallEvent $event): ?Type
    {
        if (! in_array($event->name, PaginateMethodsReturnTypeExtension::PAGINATE_METHODS)) {
            return null;
        }

        $shouldBeHandled = $event->getInstance() instanceof UnknownType
            || $this->isQueryLike($event->getInstance())
            || $event->getDefinition()?->getMethodDefinition($event->name)?->definingClassName === Builder::class;

        if (! $shouldBeHandled) {
            return null;
        }

        return $this->getPaginatorType($event->name);
    }

    private function getPaginatorType(string $name): Generic
    {
        $valueType = new UnknownType;

        return match ($name) {
            'paginate', 'fastPaginate' => new Generic(LengthAwarePaginator::class, [new IntegerType, $valueType]),
            'cursorPaginate' => new Generic(CursorPaginator::class, [new IntegerType, $valueType]),
            'simplePaginate', 'simpleFastPaginate' => new Generic(Paginator::class, [new IntegerType, $valueType]),
            default => throw new \Exception('Pagination method '.$name.' is not handled'),
        };
    }

    private function isQueryLike(Type $instance): bool
    {
        if (! $instance instanceof ObjectType) {
            return false;
        }

        return $this->isQueryLikeClass($instance->name);
    }

    private function isQueryLikeClass(string $class): bool
    {
        return collect([
            EloquentBuilder::class,
            BaseBuilder::class,
            BelongsToMany::class,
            HasManyThrough::class,
            Model::class,
        ])->some(fn (string $queryClass) => is_a($class, $queryClass, true));
    }
}
