<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Support\Type\TypeHelper;
use PhpParser\Node;

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

test('create type from type node', function ($node, string $expectedType) {
    $type = TypeHelper::createTypeFromTypeNode($node);

    expect($type->toString())->toBe($expectedType);
})->with([
    [new Node\Identifier('int'), 'int'],
    [new Node\Identifier('string'), 'string'],
    [new Node\Identifier('bool'), 'boolean'],
    [new Node\Identifier('true'), 'boolean(true)'],
    [new Node\Identifier('false'), 'boolean(false)'],
    [new Node\Identifier('float'), 'float'],
    [new Node\Identifier('array'), 'array<mixed>'],
    [new Node\Identifier('null'), 'null'],
    [new Node\Name('App\\Models\\User'), 'App\\Models\\User'],
    [new Node\NullableType(new Node\Identifier('string')), 'null|string'],
    [new Node\UnionType([
        new Node\Identifier('int'),
        new Node\Identifier('string'),
        new Node\Identifier('null'),
    ]), 'int|string|null'],
]);

enum Foo_TypeHelperTest: string
{
    case Foo = 'f';
    case Bar = 'b';
}
