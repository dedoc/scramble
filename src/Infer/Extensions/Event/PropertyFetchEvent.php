<?php

namespace Dedoc\Scramble\Infer\Extensions\Event;

use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;

class PropertyFetchEvent
{
    public function __construct(
        public readonly ObjectType $instance,
        public readonly string $name,
        public readonly Scope $scope,
    ) {}

    public function getDefinition(): ?ClassDefinitionContract
    {
        return $this->scope->index->getClass($this->getInstance()->name);
    }

    public function getInstance(): ObjectType
    {
        return $this->instance;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
