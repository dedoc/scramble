<?php

namespace Dedoc\Scramble\Infer\Contracts;

interface ClassAutoResolvingDefinition extends ClassDefinition
{
    public function getMethod(string $name): ?FunctionLikeAutoResolvingDefinition;
}
