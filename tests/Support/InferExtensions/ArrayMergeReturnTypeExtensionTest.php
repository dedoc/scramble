<?php

use Dedoc\Scramble\Support\InferExtensions\ArrayMergeReturnTypeExtension;

it('infers array_merge type', function () {
    $type = getStatementType('array_merge(["foo" => 23], ["bar" => "baz"])', [
        new ArrayMergeReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('array{foo: int(23), bar: string(baz)}');
});

it('infers array_merge type when arrays have same keys', function () {
    $type = getStatementType('array_merge(["foo" => 23], ["foo" => "baz"])', [
        new ArrayMergeReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('array{foo: string(baz)}');
});
