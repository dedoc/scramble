<?php

namespace Dedoc\Scramble\Support\TypeHandlers;

use Dedoc\Scramble\Support\Generator\Types\Type;

interface TypeHandler
{
    public static function shouldHandle($node);

    public function handle(): ?Type;
}
