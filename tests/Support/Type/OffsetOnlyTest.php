<?php

use Illuminate\Support\Arr;

it('handles array only', function () {
    $a = ['foo' => 42, 'bar' => 'baz'];
    $result = Arr::only($a, ['foo']);

    expect($result)->toHaveType('array{foo: int(42)}');
});

it('handles array except via offset unset type', function () {
    $a = ['foo' => 42, 'bar' => 'baz'];
    $result = Arr::except($a, ['foo']);

    expect($result)->toHaveType('array{bar: string(baz)}');
});
