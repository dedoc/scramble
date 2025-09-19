<?php

namespace Dedoc\Scramble\Support\Type\Contracts;

interface LiteralString extends LiteralType
{
    public function getValue(): string;
}
