<?php

namespace Dedoc\Scramble\Support\Generator\Types;

class NullType extends Type
{
    public function __construct()
    {
        parent::__construct('null');
    }
}
