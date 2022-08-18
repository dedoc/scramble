<?php

namespace Dedoc\ApiDocs\Support\Generator\Types;

class IntegerType extends NumberType
{
    public function __construct()
    {
        parent::__construct('integer');
    }
}
