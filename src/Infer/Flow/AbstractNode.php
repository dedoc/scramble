<?php

namespace Dedoc\Scramble\Infer\Flow;

abstract class AbstractNode implements Node
{
    public function __construct(
        public array $predecessors = [],
        public array $successors = [],
    )
    {
    }

    public function predecessors(): array
    {
        return $this->predecessors;
    }

    public function successors(): array
    {
        return $this->successors;
    }
}
