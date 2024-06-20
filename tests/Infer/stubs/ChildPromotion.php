<?php

namespace Dedoc\Scramble\Tests\Infer\stubs;

class _ParentPromotion
{
    public function __construct(public int $foo, public string $wow) {}
}

class ChildPromotion extends _ParentPromotion
{
    public function __construct(public string $bar, string $wow)
    {
        parent::__construct(42, $wow);
    }
}
