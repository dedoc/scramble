<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition as FunctionLikeDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeResolver;
use Dedoc\Scramble\Support\Type\FunctionType;

class FunctionLikeDeferredDefinition implements FunctionLikeDefinitionContract
{
    private ?FunctionType $resolvedIncompleteType = null;
    private ?FunctionType $resolvedType = null;

    public function __construct(
        public readonly FunctionLikeDefinitionContract $definition,
        public readonly FunctionLikeAstDefinitionBuilder $builder,
        public readonly Index $index,
    )
    {
    }

    public function getType(): FunctionType
    {
        if ($this->resolvedType) {
            return $this->resolvedType;
        }

        $incompleteType = $this->getIncompleteType();

        return $this->resolvedType = (new IncompleteTypeResolver($this->index))->resolve($incompleteType);
    }

    public function getIncompleteType(): FunctionType
    {
        if ($this->resolvedIncompleteType) {
            return $this->resolvedIncompleteType;
        }

        $definition = $this->builder->build();

        return $this->resolvedIncompleteType = $definition->getType();
    }
}
