<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\UnknownType;

class ConstFetchTypeGetter
{
    public function __invoke(Scope $scope, string $className, string $constName)
    {
        if ($constName === 'class') {
            return new LiteralStringType($className);
        }

        return new UnknownType('ConstFetchTypeGetter is not yet implemented fully for non-class const fetches.');
    }
}
