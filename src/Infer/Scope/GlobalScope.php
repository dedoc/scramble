<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;

class GlobalScope extends Scope
{
    public function __construct(?Index $index = null)
    {
        parent::__construct(
            $index ?: app()->make(Index::class), // ???
            new NodeTypesResolver,
            new ScopeContext,
            new FileNameResolver(tap(new NameContext(new Throwing), fn (NameContext $nc) => $nc->startNamespace())),
        );
    }
}
