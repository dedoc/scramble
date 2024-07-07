<?php

namespace Dedoc\Scramble\Tests\Infer\stubs;

class _Parent
{
    public int $foo;

    public string $wow;

    public function __construct(int $foo, string $wow)
    {
        $this->foo = $foo;
        $this->wow = $wow;
    }
}

class Child extends _Parent
{
    public string $bar;

    public function __construct(string $bar, string $wow, $fooParam)
    {
        parent::__construct(42, $wow);
        $this->bar = $bar;
    }
}
