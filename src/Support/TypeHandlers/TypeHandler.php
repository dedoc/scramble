<?php

namespace Dedoc\Documentor\Support\TypeHandlers;

use Dedoc\Documentor\Support\Generator\Types\Type;

interface TypeHandler
{
    public static function shouldHandle($node);

    public function handle(): ?Type;
}
