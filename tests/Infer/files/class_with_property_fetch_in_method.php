<?php

class Foo
{
    public $prop;

    public function __construct($a)
    {
        $this->prop = $a;
    }

    public function foo()
    {
        return $this->prop;
    }
}
