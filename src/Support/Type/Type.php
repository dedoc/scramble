<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use PhpParser\Node\Expr\MethodCall;

interface Type
{
    public function setAttribute(string $key, $value): void;

    public function hasAttribute(string $key): bool;

    public function getAttribute(string $key);

    public function isInstanceOf(string $className);

    public function getPropertyFetchType(string $propertyName): Type;

    public function getMethodCallType(string $methodName, MethodCall $node, Scope $scope): Type;

    public function children(): array;

    public function isSame(self $type);

    public function toString(): string;
}
