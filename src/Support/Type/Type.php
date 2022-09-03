<?php

namespace Dedoc\Scramble\Support\Type;

interface Type
{
    public function setAttribute(string $key, $value): void;

    public function hasAttribute(string $key): bool;

    public function getAttribute(string $key);

    public function isInstanceOf(string $className);

    public function getPropertyFetchType(string $propertyName): Type;

    public function getMethodCallType(string $methodName): Type;

    public function nodes(): array;

    public function children(): array;

    public function isSame(self $type);

    public function toString(): string;
}
