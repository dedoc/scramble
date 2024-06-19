<?php

namespace Dedoc\Scramble\Support\Type\SideEffects;

use Dedoc\Scramble\Support\Type\Type;

class ParentConstructCall
{
    /**
     * @param  Type[]  $arguments  The list of arguments Types passed to the parent::__construct call.
     */
    public function __construct(
        public array $arguments,
    ) {}
}
