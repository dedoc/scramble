<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Scope;

interface Type
{
    public function setAttribute(string $key, $value): void;

    public function hasAttribute(string $key): bool;

    public function getAttribute(string $key);

    public function isInstanceOf(string $className);

    public function nodes(): array;

    public function getPropertyType(string $propertyName, Scope $scope): Type;

    public function getMethodDefinition(string $methodName, Scope $scope = new GlobalScope): ?FunctionLikeDefinition;

    public function isSame(self $type);

    public function toString(): string;
}
