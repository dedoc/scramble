<?php

namespace Dedoc\Documentor\Support\Generator\Types;

class IntegerType extends NumberType
{
    public function __construct()
    {
        parent::__construct('integer');
    }
}
