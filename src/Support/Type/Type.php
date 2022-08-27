<?php

namespace Dedoc\Scramble\Support\Type;

interface Type
{
    public function isSame(self $type);

    public function toString(): string;
}
