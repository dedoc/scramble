<?php

class Foo
{
    public function foo()
    {
        return $this;
    }

    public function bar()
    {
        return $this->foo()->foo()->foo()->one();
    }

    public function one()
    {
        return 1;
    }
}
