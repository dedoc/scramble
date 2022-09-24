<?php

namespace Dedoc\Scramble\Infer\Handler;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

interface CreatesScope
{
    public function createScope(Scope $scope, Node $node): Scope;
}
