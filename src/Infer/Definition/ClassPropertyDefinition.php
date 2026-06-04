<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Support\Type\Type;

class ClassPropertyDefinition
{
    public function __construct(
        public Type $type,
        public ?Type $defaultType = null,
        public bool $isStatic = false,
        public PropertyVisibility $visibility = PropertyVisibility::Public,
    ) {}
}
