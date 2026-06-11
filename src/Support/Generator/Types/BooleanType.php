<?php

namespace Dedoc\Scramble\Support\Generator\Types;

class BooleanType extends Type
{
    public function __construct()
    {
        parent::__construct('boolean');
    }

    public function matches($value): bool
    {
        return is_bool($value);
    }
}
