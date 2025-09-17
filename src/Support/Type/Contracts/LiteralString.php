<?php

namespace Dedoc\Scramble\Support\Type\Contracts;

interface LiteralString extends Literal
{
    public function getValue(): string;
}
