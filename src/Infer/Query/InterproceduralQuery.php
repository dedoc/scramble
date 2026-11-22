<?php

namespace Dedoc\Scramble\Infer\Query;

use Dedoc\Scramble\Infer\Definition\FunctionLikeAstDefinition;
use Dedoc\Scramble\Infer\Services\ShallowTypeResolver;

class InterproceduralQuery
{
    public function __construct(
        public readonly FunctionLikeAstDefinition $definition,
        private readonly ShallowTypeResolver $shallowTypeResolver,
    ) {}

    public function instancesOf(string $class): InstanceQuery
    {
        return new InstanceQuery($this->calls(), $class, $this->shallowTypeResolver);
    }

    public function calls(): CallQuery
    {
        return new CallQuery($this->definition, $this->shallowTypeResolver);
    }
}
