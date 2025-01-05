<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeAutoResolvingDefinition as FunctionLikeAutoResolvingDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition as FunctionLikeDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition as FunctionLikeDefinitionData;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeResolver;
use Dedoc\Scramble\Support\Type\FunctionType;

class FunctionLikeAutoResolvingDefinition implements FunctionLikeAutoResolvingDefinitionContract
{
    private ?FunctionType $resolvedType = null;

    public function __construct(
        public readonly FunctionLikeDefinitionContract $definition,
        public readonly Index $index,
    ) {}

    public function getData(): FunctionLikeDefinitionData
    {
        return $this->definition->getData();
    }

    public function getType(): FunctionType
    {
        if ($this->resolvedType) {
            return $this->resolvedType;
        }

        return $this->resolvedType = (new IncompleteTypeResolver($this->index))
            ->resolve($this->definition->getType());
    }

    public function getIncompleteType(): FunctionType
    {
        return $this->definition->getType();
    }
}
