<?php

namespace Dedoc\Scramble\Support\Type\Reference\Dependency;

class ClassDependency implements Dependency
{
    public function __construct(
        public string $class,
    ) {
    }
}
