<?php

namespace Dedoc\Scramble\Infer\Configuration;

class ClassLikeAndChildren
{
    public function __construct(public readonly string $class)
    {
    }

    public function matches(string $class): bool
    {
        return is_a($class, $this->class, true);
    }
}
