<?php

it('handles array fetch', function () {
    expect(["a" => 1]["a"])->toHaveType('int(1)');
});

it('handles array set type', function () {
    $a = [];
    $a['foo'] = 42;

    expect($a)->toHaveType('array{foo: int(42)}');
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
