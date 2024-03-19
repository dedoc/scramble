<?php

use Dedoc\Scramble\Support\InferExtensions\ArrayKeysReturnTypeExtension;

it('infers string array keys return type from literal string keys', function () {
    $type = getStatementType('array_keys(["foo" => 1, "bar" => 2])', [new ArrayKeysReturnTypeExtension]);

    expect($type->toString())->toBe('array{0: string(foo), 1: string(bar)}');
});

it('infers all array keys return type from non-string keys', function () {
    $type = getStatementType('array_keys([1, "foo", "bar" => 42])', [new ArrayKeysReturnTypeExtension]);

    expect($type->toString())->toBe('array{0: int(0), 1: int(1), 2: string(bar)}');
});
