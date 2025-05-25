<?php

namespace Dedoc\Scramble\Infer\Extensions\Event;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\Concerns\ArgumentTypesAware;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

class AnyMethodCallEvent
{
    use ArgumentTypesAware;

    /**
     * @param  array<array-key, Type>  $arguments
     */
    public function __construct(
        public readonly Type $instance,
        public readonly string $name,
        public readonly Scope $scope,
        public readonly array $arguments,
        public readonly ?string $methodDefiningClassName,
    ) {}

    public function getDefinition(): ?ClassDefinition
    {
        return $this->instance instanceof ObjectType
            ? $this->scope->index->getClassDefinition($this->instance->name)
            : null;
    }

    public function getInstance(): Type
    {
        return $this->instance;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
