<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node\Expr\Variable;

class ValueFact
{
    public function __construct(
        public Variable $expr,
        public ?Type $equals = null,
        public ?Type $notEquals = null,
    ) {}
}
