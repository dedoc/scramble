<?php

namespace Dedoc\Scramble\Tests\Infer\stubs;

class _ParentThis
{
    public function getThis()
    {
        return $this;
    }
}

class ChildParentThis extends _ParentThis
{
}
