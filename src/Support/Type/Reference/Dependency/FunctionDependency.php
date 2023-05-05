<?php

namespace Dedoc\Scramble\Support\Type\Reference\Dependency;

class FunctionDependency implements Dependency
{
    public function __construct(
        public string $name,
    ) {
    }
}
