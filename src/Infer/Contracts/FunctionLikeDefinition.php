<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Support\Type\FunctionType;

interface FunctionLikeDefinition extends Definition
{
    public function getType(): FunctionType;
}
