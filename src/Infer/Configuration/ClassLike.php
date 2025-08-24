<?php

namespace Dedoc\Scramble\Infer\Configuration;

class ClassLike
{
    public function __construct(public readonly string $class)
    {
    }

    public function matches(string $class): bool
    {
        return $class === $this->class;
    }
}
