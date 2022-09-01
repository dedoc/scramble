<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Generator\Types\TypeAttributes;
use Dedoc\Scramble\Support\Infer\Scope\Scope;
use PhpParser\Node\Expr\MethodCall;

abstract class AbstractType implements Type
{
    use TypeAttributes;

    public function getPropertyFetchType(string $propertyName): Type
    {
        return new UnknownType('Cannot find property fetch type.');
    }

    public function getMethodCallType(string $methodName, MethodCall $node, Scope $scope): Type
    {
        return new UnknownType('Cannot find method call type.');
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
