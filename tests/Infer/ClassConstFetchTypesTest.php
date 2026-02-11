<?php

use Dedoc\Scramble\Support\Type\ClassConstantType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;

it(
    'infers class const fetch types',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['$var::class', 'string'],
    ['(new SomeType)::class', 'class-string<SomeType>'],
    ['SomeType::class', 'class-string<SomeType>'],
    ['Enum_ClassConstFetchTypesTest::FOO', 'Enum_ClassConstFetchTypesTest::FOO'],
]);

it('infers class const type when "class_constants_as_const" is set to true', function ($flag, $classString) {
    config()->set('scramble.class_constants_as_const', $flag);

    $statement = 'ClassConstant_ClassConstFetchTypesTest::FOO';
    expect(getStatementType($statement))->toBeInstanceOf($classString);
})->with([
    [true, ClassConstantType::class],
    [false, LiteralStringType::class],
]);

enum Enum_ClassConstFetchTypesTest: string
{
    case FOO = 'foo';
    case BAR = 'bar';
}

class ClassConstant_ClassConstFetchTypesTest
{
    public const FOO = 'foo';
}
