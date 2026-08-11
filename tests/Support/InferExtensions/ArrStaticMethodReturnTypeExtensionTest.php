<?php

use Dedoc\Scramble\Support\InferExtensions\ArrStaticMethodReturnTypeExtension;
use Illuminate\Support\Arr;

it('infers Arr::except type with a single key', function () {
    $type = getStatementType(Arr::class.'::except(["foo" => 23, "bar" => "baz"], "foo")', [
        new ArrStaticMethodReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('array{bar: string(baz)}');
});

it('infers Arr::except type with multiple keys', function () {
    $type = getStatementType(Arr::class.'::except(["foo" => 23, "bar" => "baz", "qux" => true], ["foo", "qux"])', [
        new ArrStaticMethodReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('array{bar: string(baz)}');
});

it('infers Arr::only type with a single key', function () {
    $type = getStatementType(Arr::class.'::only(["foo" => 23, "bar" => "baz"], "foo")', [
        new ArrStaticMethodReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('array{foo: int(23)}');
});

it('infers Arr::only type with multiple keys', function () {
    $type = getStatementType(Arr::class.'::only(["foo" => 23, "bar" => "baz", "qux" => true], ["foo", "bar"])', [
        new ArrStaticMethodReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('array{foo: int(23), bar: string(baz)}');
});

it('handles Arr::except via toHaveType', function () {
    $a = ['foo' => 42, 'bar' => 'baz'];

    $result = Arr::except($a, 'foo');

    expect($result)->toHaveType('array{bar: string(baz)}');
});

it('handles Arr::only via toHaveType', function () {
    $a = ['foo' => 42, 'bar' => 'baz'];

    $result = Arr::only($a, 'foo');

    expect($result)->toHaveType('array{foo: int(42)}');
});

it('handles Arr::only with keys array argument', function () {
    $a = ['foo' => 42, 'bar' => 'baz'];
    $keys = ['foo', 'bar'];

    $result = Arr::only($a, $keys);

    expect($result)->toHaveType('array{foo: int(42), bar: string(baz)}');
});

it('handles Arr::except with keys array argument', function () {
    $a = ['foo' => 42, 'bar' => 'baz'];
    $keys = ['foo'];

    $result = Arr::except($a, $keys);

    expect($result)->toHaveType('array{bar: string(baz)}');
});
