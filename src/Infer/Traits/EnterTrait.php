<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

trait EnterTrait
{
    public function enter(Node $node, Scope $scope): void
    {
    }
}
