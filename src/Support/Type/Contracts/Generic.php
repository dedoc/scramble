<?php

namespace Dedoc\Scramble\Support\Type\Contracts;

use Dedoc\Scramble\Support\Type\Type;

interface Generic
{
    /**
     * @return Type[]
     */
    public function getTypes(): array;
}
