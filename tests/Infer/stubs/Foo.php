<?php

namespace Dedoc\Scramble\Tests\Infer\stubs;

class Foo extends Bar
{
    public $prop;

    public function bar()
    {
        return $this->foo();
    }

    public function foo()
    {
        return 243;
    }

    public function fqn()
    {
        return Foo::class;
    }
}

class Bar
{
    public $propB;
}
