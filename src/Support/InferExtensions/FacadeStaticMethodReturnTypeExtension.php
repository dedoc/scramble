<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\AutoResolvingArgumentTypeBag;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;

class FacadeStaticMethodReturnTypeExtension implements StaticMethodReturnTypeExtension
{
    public const FACADE_ROOT_OBJECTS = [
        DB::class => DatabaseManager::class,
    ];

    public function shouldHandle(string $name): bool
    {
        return array_key_exists($name, self::FACADE_ROOT_OBJECTS);
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        $rootClass = self::FACADE_ROOT_OBJECTS[$event->getCallee()] ?? null;

        if (! $rootClass) {
            return null;
        }

        return ReferenceTypeResolver::getInstance()->resolve(
            $event->scope,
            new MethodCallReferenceType(
                new ObjectType($rootClass),
                $event->getName(),
                $event->arguments instanceof AutoResolvingArgumentTypeBag
                    ? $event->arguments->allUnresolved()
                    : $event->arguments->all(),
            ),
        );
    }
}
