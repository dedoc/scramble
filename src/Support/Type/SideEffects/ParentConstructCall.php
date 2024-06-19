<?php

namespace Dedoc\Scramble\Support\Type\SideEffects;

use Dedoc\Scramble\Support\Type\Type;

class ParentConstructCall
{
    /**
     * @param array{0: array{0: ?string, 1: int}, 1: Type}[] $arguments The list of arguments passed to the parent::__construct call.
     *        The first tuple's value is name/index argument pair, and the second one is argument's type.
     */
    public function __construct(
        public array $arguments,
    ) {
    }
}
