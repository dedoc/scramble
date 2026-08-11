<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\StringType;

it('compares keyed arrays structurally while ignoring attributes', function () {
    $leftItem = new ArrayItemType_(
        key: 'name',
        value: new StringType,
        isOptional: true,
        shouldUnpack: true,
        keyType: new LiteralStringType('name'),
    );
    $leftItem->setAttribute('source', 'left');

    $rightItem = new ArrayItemType_(
        key: 'name',
        value: new StringType,
        isOptional: true,
        shouldUnpack: true,
        keyType: new LiteralStringType('name'),
    );
    $rightItem->setAttribute('source', 'right');

    $left = new KeyedArrayType([$leftItem], isList: false);
    $right = new KeyedArrayType([$rightItem], isList: false);
    $left->setAttribute('source', 'left');
    $right->setAttribute('source', 'right');

    expect($left->isSame($right))->toBeTrue()
        ->and($right->isSame($left))->toBeTrue();
});

it('distinguishes keyed arrays with different structures', function () {
    $base = new KeyedArrayType([
        new ArrayItemType_(
            key: 'name',
            value: new StringType,
            keyType: new LiteralStringType('name'),
        ),
    ], isList: false);

    $differentKey = $base->clone();
    $differentKey->items[0]->key = 'title';

    $differentValue = $base->clone();
    $differentValue->items[0]->value = new IntegerType;

    $differentOptionality = $base->clone();
    $differentOptionality->items[0]->isOptional = true;

    $differentUnpacking = $base->clone();
    $differentUnpacking->items[0]->shouldUnpack = true;

    $differentKeyType = $base->clone();
    $differentKeyType->items[0]->keyType = new IntegerType;

    $differentArrayKind = $base->clone();
    $differentArrayKind->isList = true;

    expect($base->isSame($differentKey))->toBeFalse()
        ->and($base->isSame($differentValue))->toBeFalse()
        ->and($base->isSame($differentOptionality))->toBeFalse()
        ->and($base->isSame($differentUnpacking))->toBeFalse()
        ->and($base->isSame($differentKeyType))->toBeFalse()
        ->and($base->isSame($differentArrayKind))->toBeFalse();
});

it('compares keyed array items in order', function () {
    $first = new KeyedArrayType([
        new ArrayItemType_('first', new StringType),
        new ArrayItemType_('second', new IntegerType),
    ]);
    $reversed = new KeyedArrayType([
        new ArrayItemType_('second', new IntegerType),
        new ArrayItemType_('first', new StringType),
    ]);

    expect($first->isSame($reversed))->toBeFalse();
});
