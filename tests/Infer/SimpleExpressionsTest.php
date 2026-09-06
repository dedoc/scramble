<?php

use Dedoc\Scramble\Support\Type\IntegerType;

it(
    'infers simple types',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['null', 'null'],
    ['true', 'boolean(true)'],
    ['false', 'boolean(false)'],
    ['1', 'int(1)'],
    ['"foo"', 'string(foo)'],
    ['157.50', 'float(157.5)'],
]);

it(
    'infers boolean operations',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['! $some', 'boolean'],
    ['!! $some', 'boolean'],
    ['$a > $b', 'boolean'],
    ['$a >= $b', 'boolean'],
    ['$a < $b', 'boolean'],
    ['$a <= $b', 'boolean'],
    ['$a != $b', 'boolean'],
    ['$a !== $b', 'boolean'],
    ['$a == $b', 'boolean'],
    ['$a === $b', 'boolean'],
]);

it(
    'infers arithmetic operations',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['1 + 2', 'int(3)'],
    ['1 - 2', 'int(-1)'],
    ['2 * 3', 'int(6)'],
    ['5 % 2', 'int(1)'],
    ['2 ** 3', 'int(8)'],
]);

it(
    'infers concatenation',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['"foo" . "bar"', 'string(foobar)'],
    ['"foo" . 1', 'string(foo1)'],
    ['"foo" . $a', 'string(`foo${unknown}`)'],
    ['$a . "bar"', 'string(`${unknown}bar`)'],
    ['$a . $b', 'string(`${unknown}${unknown}`)'],
    ['"foo" . $a . "bar" . "baz"', 'string(`foo${unknown}barbaz`)'],
    ['\'foo`bar\' . $a', 'string(`foo\\`bar${unknown}`)'],
    ['\'${x}\' . $a', 'string(`\\${x}${unknown}`)'],
]);

it('infers arithmetic of int-returning method calls', function () {
    $foo = Foo_SimpleExpressionsTest::class;

    expect(getStatementType("(new {$foo})->price() + (new {$foo})->fee()"))
        ->toBeInstanceOf(IntegerType::class);
});

it('infers clone as the type of the cloned expression', function () {
    $expression = 'new '.Foo_SimpleExpressionsTest::class;

    expect(getStatementType("clone {$expression}")->toString())
        ->toBe(getStatementType($expression)->toString());
});

it('infers method calls on cloned objects', function () {
    expect(getStatementType('(clone new '.Foo_SimpleExpressionsTest::class.')->price()'))
        ->toBeInstanceOf(IntegerType::class);
});

it(
    'doesnt fail on dynamic static fetch',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['Something::{$v}', 'unknown'],
]);

class Foo_SimpleExpressionsTest
{
    public function price(): int
    {
        return 100;
    }

    public function fee(): int
    {
        return 5;
    }
}

// @todo
// casts test (int, float, bool, string)
// array with literals test (int, float, bool, string)
