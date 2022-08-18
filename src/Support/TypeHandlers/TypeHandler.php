<?php

namespace Dedoc\ApiDocs\Support\TypeHandlers;

use Dedoc\ApiDocs\Support\Generator\Types\Type;

interface TypeHandler
{
    public static function shouldHandle($node);

    public function handle(): ?Type;
}
