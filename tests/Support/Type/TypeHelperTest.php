<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Support\Type\TypeHelper;

test('create type from value', function ($value, string $expectedType) {
    $type = TypeHelper::createTypeFromValue($value);

    expect($type->toString())->toBe($expectedType);
})->with([
    [1, 'int(1)'],
    ['foo', 'string(foo)'],
    [[1, 2, 3], 'list{int(1), int(2), int(3)}'],
]);

test('create type from enum value', function () {
    $type = TypeHelper::createTypeFromValue([
        Foo_TypeHelperTest::Foo,
        Foo_TypeHelperTest::Bar,
    ]);

    expect($type->toString())->toBe('list{Dedoc\Scramble\Tests\Support\Type\Foo_TypeHelperTest::Foo, Dedoc\Scramble\Tests\Support\Type\Foo_TypeHelperTest::Bar}');
});

enum Foo_TypeHelperTest: string
{
    case Foo = 'f';
    case Bar = 'b';
}
