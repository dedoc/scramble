<?php

namespace Dedoc\Scramble\Support\Type\Reference;

use Dedoc\Scramble\Support\Type\Type;

class ArrayDimFetchReferenceType extends AbstractReferenceType
{
    public function __construct(
        public Type $var,
        public Type $dim,
    ) {}

    public function nodes(): array
    {
        return ['var', 'dim'];
    }

    public function toString(): string
    {
        $varType = $this->var->toString();
        $dimType = $this->dim->toString();

        return "{$varType}[$dimType]";
    }
}
