<?php

namespace Dedoc\Scramble\Infer\Extensions\Event;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\Concerns\ArgumentTypesAware;
use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node\Expr\CallLike;

class SideEffectCallEvent
{
    use ArgumentTypesAware;

    public function __construct(
        public readonly FunctionLikeDefinition $definition,
        public readonly FunctionLikeDefinition $calledDefinition,
        public readonly CallLike $node,
        public readonly Scope $scope,
        public readonly ArgumentTypeBag $arguments,
    ) {}
}
