<?php

namespace Dedoc\Scramble\Infer\Extensions\Event;

use Dedoc\Scramble\Infer\Extensions\Event\Concerns\ArgumentTypesAware;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ArgumentTypeBag;

class FunctionCallEvent
{
    use ArgumentTypesAware;

    public function __construct(
        public readonly string $name,
        public readonly Scope $scope,
        public readonly ArgumentTypeBag $arguments,
    ) {}

    public function getDefinition()
    {
        return $this->scope->index->getFunctionDefinition($this->name);
    }

    public function getName()
    {
        return $this->name;
    }
}
