<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Collection;

class TypeEffect
{
    public function __construct(
        public Type $type,
        public Collection $facts,
    ) {}
}
