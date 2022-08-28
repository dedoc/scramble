<?php

namespace Dedoc\Scramble\Support\Type;

interface Type
{
    public function isInstanceOf(string $className);

    public function getPropertyFetchType(string $propertyName): Type;

    public function isSame(self $type);

    public function toString(): string;
}
