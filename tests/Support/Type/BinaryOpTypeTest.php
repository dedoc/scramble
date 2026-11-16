<?php

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\BinaryOpType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;

it('fills an optional key of the left array from the right array', function () {
    $type = new BinaryOpType(
        new KeyedArrayType([new ArrayItemType_('x', new LiteralIntegerType(1), isOptional: true)]),
        new KeyedArrayType([new ArrayItemType_('x', new LiteralStringType('b'))]),
        '+',
    );

    expect($type->resolve()->toString())->toBe('array{x: int(1)|string(b)}');
});

it('keeps the keys of an added array whose numeric keys are out of order', function () {
    $type = (new BinaryOpType(
        new KeyedArrayType([new ArrayItemType_(1, new LiteralStringType('a'))]),
        new KeyedArrayType([new ArrayItemType_(0, new LiteralStringType('b'))]),
        '+',
    ))->resolve();

    expect($type->isList)->toBeFalse()
        ->and(array_map(fn (ArrayItemType_ $item) => $item->key, $type->items))->toBe([1, 0]);
});
