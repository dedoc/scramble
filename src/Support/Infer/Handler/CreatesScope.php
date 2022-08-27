<?php

namespace Dedoc\Scramble\Support\Infer\Handler;

use Dedoc\Scramble\Support\Infer\Scope\Scope;

interface CreatesScope
{
    public function createScope(Scope $scope): Scope;
}
