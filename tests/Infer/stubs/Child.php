<?php

namespace Dedoc\Scramble\Tests\Infer\stubs;

class _Parent {
    public int $foo;
    public function __construct(int $foo)
    {
        $this->foo = $foo;
    }
}

class Child extends _Parent {
    public string $bar;
    public function __construct(string $bar, string $wow, $fooParam)
    {
        parent::__construct(42);
        $this->bar = $bar;
    }
}
