<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;

interface CreatesScope
{
    public function createScope(Scope $scope): Scope;
}
