<?php

namespace Dedoc\Scramble\Support\Type;

abstract class AbstractType implements Type
{
    use TypeAttributes;

    public function getPropertyFetchType(string $propertyName): Type
    {
        return new UnknownType('Cannot find property fetch type.');
    }

    public function getMethodCallType(string $methodName): Type
    {
        return new UnknownType('Cannot find method call type.');
    }

    public function nodes(): array
    {
        return [];
    }

    public function children(): array
    {
        return [];
    }

    public function isInstanceOf(string $className)
    {
        return false;
    }
}
