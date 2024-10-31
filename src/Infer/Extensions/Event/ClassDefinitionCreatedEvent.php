<?php

namespace Dedoc\Scramble\Infer\Extensions\Event;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;

class ClassDefinitionCreatedEvent
{
    public function __construct(
        public readonly string $name,
        public readonly ClassDefinition $classDefinition,
    ) {}
}
