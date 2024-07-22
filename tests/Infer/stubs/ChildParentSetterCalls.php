<?php

namespace Dedoc\Scramble\Tests\Infer\stubs;

class _ParentChildParentSetterCalls
{
    public function __construct(public string $foo, public string $wow) {}

    public function setFoo(string $foo)
    {
        $this->foo = $foo;

        return $this;
    }

    public function setWow(string $wow)
    {
        $this->wow = $wow;

        return $this;
    }
}

class ChildParentSetterCalls extends _ParentChildParentSetterCalls
{
    public function __construct(public string $bar, string $wow)
    {
        parent::__construct('constructor call', $wow);

        $this
            ->setFoo('from ChildParentSetterCalls constructor')
            ->setWow('from ChildParentSetterCalls wow');
    }
}
