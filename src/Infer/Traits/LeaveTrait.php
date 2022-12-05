<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

trait LeaveTrait
{
    public function leave(Node $node, Scope $scope): void
    {
    }
}
