<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition as FunctionLikeDefinitionData;
use Dedoc\Scramble\Support\Type\FunctionType;

interface FunctionLikeDefinition extends Definition
{
    public function getType(): FunctionType;

    public function getData(): FunctionLikeDefinitionData;
}
