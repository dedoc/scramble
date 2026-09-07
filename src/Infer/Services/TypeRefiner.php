<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Support\Type\Type;

class TypeRefiner
{
    public function refine(Type $declared, Type $inferred): Type
    {
        if ($declared->accepts($inferred)) {
            return $inferred;
        }

        return $declared;
    }
}
