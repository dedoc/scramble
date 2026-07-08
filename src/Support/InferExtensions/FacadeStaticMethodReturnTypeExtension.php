<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\AutoResolvingArgumentTypeBag;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Facades\Facade;

class FacadeStaticMethodReturnTypeExtension implements StaticMethodReturnTypeExtension
{
    /** @var array<string, string|null> */
    private static array $rootClassCache = [];

    public function shouldHandle(string $name): bool
    {
        return is_a($name, Facade::class, true);
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        $rootClass = $this->getRootClass($event->getCallee());

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

    private function getRootClass(string $facadeClass): ?string
    {
        if (array_key_exists($facadeClass, self::$rootClassCache)) {
            return self::$rootClassCache[$facadeClass];
        }

        return self::$rootClassCache[$facadeClass] = $this->getFreshRootClass($facadeClass);
    }

    private function getFreshRootClass(string $facadeClass): ?string
    {
        $seeTag = SchemaClassDocReflector::createFromClassName($facadeClass)->getTagValue('@see');
        $rootClass = ltrim(explode("\n", $seeTag->value ?? '')[0], '\\');

        if (! $rootClass) {
            return null;
        }

        return class_exists($rootClass) ? $rootClass : null;
    }
}
