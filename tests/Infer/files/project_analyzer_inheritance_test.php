<?php

namespace App;

class Foo extends Bar {
    public $fooProp;

    public function __construct($fooProp, $barProp)
    {
        parent::__construct(barProp: $barProp);
        $this->fooProp = $fooProp;
    }

    public function props()
    {
        return [$this->fooProp, $this->barProp];
    }
}

class Bar {
    public $barProp;

    public function __construct($barProp)
    {
        $this->barProp = $barProp;
    }

}

//
//trait Baz {
//    public $bazProp;
//
//    public function setBazProp ($bazProp)
//    {
//        $this->bazProp = $bazProp;
//    }
//}
//
//var_dump((new Foo(1,2,3))->props());
