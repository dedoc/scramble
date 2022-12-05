<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

interface ScopeCreator
{
    public function createScope(Scope $scope, Node $node): Scope;
}
