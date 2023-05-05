<?php

namespace Dedoc\Scramble\Support\Type\Reference\Dependency;

class MethodDependency implements Dependency
{
    public function __construct(
        public string $class,
        public string $name,
    ) {
    }
}
