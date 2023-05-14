<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;

class GlobalScope extends Scope
{
    public function __construct()
    {
        parent::__construct(
            app()->make(Index::class), // ???
            new NodeTypesResolver(),
            new ScopeContext(),
            new FileNameResolver(new NameContext(new Throwing())),
        );
    }
}
