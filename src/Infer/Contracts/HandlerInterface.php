<?php

namespace Dedoc\Scramble\Infer\Contracts;

use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;

interface HandlerInterface {

    public function shouldHandle(Node $node): bool;

    public function leave(Node $node, Scope $scope): void;

    public function enter(Node $node, Scope $scope): void;

}
