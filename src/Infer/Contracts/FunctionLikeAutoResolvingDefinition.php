<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Support\Type\FunctionType;

interface FunctionLikeAutoResolvingDefinition extends FunctionLikeDefinition
{
    public function getIncompleteType(): FunctionType;
}
