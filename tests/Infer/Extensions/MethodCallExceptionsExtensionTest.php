<?php

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\ExtensionsBroker;
use Dedoc\Scramble\Infer\Extensions\MethodCallExceptionsExtension;
use Dedoc\Scramble\Support\Type\ObjectType;

it('infers exceptions from method call using method call exceptions extension', function () {
    $extension = new class implements MethodCallExceptionsExtension
    {
        public function shouldHandle(ObjectType $type): bool
        {
            return $type->name === MethodCallExceptionsExtensionTest_Service::class;
        }

        public function getMethodCallExceptions(MethodCallEvent $event): array
        {
            if ($event->name === 'doSomething') {
                return [
                    new ObjectType(\RuntimeException::class),
                    new ObjectType(\InvalidArgumentException::class),
                ];
            }

            return [];
        }
    };

    app()->singleton(
        ExtensionsBroker::class,
        fn () => new ExtensionsBroker([$extension]),
    );

    $fnType = analyzeClass(MethodCallExceptionsExtensionTest_Service::class)
        ->getClassDefinition(MethodCallExceptionsExtensionTest_Service::class)
        ->getMethodDefinition('foo')
        ->type;

    expect($fnType->exceptions)->toHaveCount(2)
        ->and($fnType->exceptions[0]->name)->toBe(\RuntimeException::class)
        ->and($fnType->exceptions[1]->name)->toBe(\InvalidArgumentException::class);
});

it('does not add exceptions when extension should not handle the type', function () {
    $extension = new class implements MethodCallExceptionsExtension
    {
        public function shouldHandle(ObjectType $type): bool
        {
            return false; // Never handle
        }

        public function getMethodCallExceptions(MethodCallEvent $event): array
        {
            return [new ObjectType(\Exception::class)];
        }
    };

    app()->singleton(
        ExtensionsBroker::class,
        fn () => new ExtensionsBroker([$extension]),
    );

    $fnType = analyzeClass(MethodCallExceptionsExtensionTest_NoHandleTest::class)
        ->getClassDefinition(MethodCallExceptionsExtensionTest_NoHandleTest::class)
        ->getMethodDefinition('foo')
        ->type;

    expect($fnType->exceptions)->toHaveCount(0);
});

class MethodCallExceptionsExtensionTest_Service
{
    public function foo()
    {
        return $this->doSomething();
    }
}

class MethodCallExceptionsExtensionTest_OtherService
{
    public function doSomething() {}
}

class MethodCallExceptionsExtensionTest_NoHandleTest
{
    public function foo()
    {
        $service = new MethodCallExceptionsExtensionTest_OtherService();
        return $service->doSomething();
    }
}
