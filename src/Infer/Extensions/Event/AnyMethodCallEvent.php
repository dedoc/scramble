<?php

namespace Dedoc\Scramble\Infer\Extensions\Event;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\Concerns\ArgumentTypesAware;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

class AnyMethodCallEvent
{
    use ArgumentTypesAware;

    public function __construct(
        public readonly Type $instance,
        public readonly string $name,
        public readonly Scope $scope,
        public readonly ArgumentTypeBag $arguments,
        public readonly ?string $methodDefiningClassName,
    ) {}

    public function getDefinition(): ?ClassDefinition
    {
        return $this->instance instanceof ObjectType
            ? $this->scope->index->getClass($this->instance->name)
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
