<?php

it('handles array fetch', function () {
    expect(["a" => 1]["a"])->toHaveType('int(1)');
});

it('handles array set type', function () {
    $a = [];
    $a['foo'] = 42;

    expect($a)->toHaveType('array{foo: int(42)}');
});

it('handles array push type', function () {
    $a = [];
    $a[] = 42;
    $a[] = 1;

    expect($a)->toHaveType('list{int(42), int(1)}');
});

it('handles array modify type', function () {
    $a = ['foo' => 23];

    $a['foo'] = 42;

    expect($a)->toHaveType('array{foo: int(42)}');
});

it('handles array deep set type', function () {
    $a = [];
    $a['foo']['bar'] = 42;

    expect($a)->toHaveType('array{foo: array{bar: int(42)}}');
});

it('handles array deep modify type', function () {
    $a = ['foo' => []];
    $a['foo']['bar'] = 42;

    expect($a)->toHaveType('array{foo: array{bar: int(42)}}');
});

it('handles array deep push type', function () {
    $a = ['foo' => []];
    $a['foo']['bar'][] = 42;
    $a['foo']['bar'][] = 1;

    expect($a)->toHaveType('array{foo: array{bar: list{int(42), int(1)}}}');
});

it('allows setting keys on template type', function () {
    $a = function ($b) {
        $b['wow'] = 42;
        return $b;
    };

    $wow = ['foo' => 'bar'];
    $wow2 = $a($wow);

    expect($wow2)->toHaveType('array{foo: string(bar), wow: int(42)}');
});

it('allows setting keys on template type with deep methods logic', function () {
    $foo = new Foo_ExpressionsTest;

    $result = $foo->setC(['foo' => 'bar']);

    expect($result)->toHaveType('array{foo: string(bar), a: int(1), b: int(2), c: int(3)}');
});

class Foo_ExpressionsTest {
    public function setA($data)
    {
        $data['a'] = 1;
        return $data;
    }
    public function setB($data)
    {
        $data = $this->setA($data);
        $data['b'] = 2;
        return $data;
    }
    public function setC($data)
    {
        $data = $this->setB($data);
        $data['c'] = 3;
        return $data;
    }
}
