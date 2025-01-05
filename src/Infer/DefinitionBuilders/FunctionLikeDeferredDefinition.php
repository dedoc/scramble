<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition as FunctionLikeDefinitionContract;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition as FunctionLikeDefinitionData;
use Dedoc\Scramble\Support\Type\FunctionType;

class FunctionLikeDeferredDefinition implements FunctionLikeDefinitionContract
{
    private ?FunctionType $astAnalyzedType = null;

    public function __construct(
        public readonly FunctionLikeDefinitionContract $definition,
        public readonly FunctionLikeAstDefinitionBuilder $builder,
    )
    {
    }

    public function getData(): FunctionLikeDefinitionData
    {
        return $this->definition->getData();
    }

    public function getType(): FunctionType
    {
        if ($this->astAnalyzedType) {
            return $this->astAnalyzedType;
        }

        return $this->astAnalyzedType = $this->builder->build()->getType();
    }
}
