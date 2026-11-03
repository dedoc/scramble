<?php

use Dedoc\Scramble\Support\Type\ConcatenatedStringType;
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
    ['1.5 * 2', 'float(3)'],
    ['4 / 2', 'int(2)'],
    ['5 / 2', 'float(2.5)'],
    ['true * 2', 'int(2)'],
    ['(int) $a + (int) $b', 'int'],
    ['(float) $a + (int) $b', 'float'],
    ['(int) $a / (int) $b', 'int|float'],
    ['(int) $a / (float) $b', 'float'],
    ['((int) $a) ** ((int) $b)', 'int|float'],
    ['(int) $a % (int) $b', 'int'],
    ['$a * 2', 'int|float'],
    ['2 * $a', 'int|float'],
    ['$a * $b', 'int|float'],
    ['$a * 1.5', 'float'],
    ['1.5 * $a', 'float'],
    ['$a + $b', 'int|float|array<mixed>'],
    ['$a + 1', 'int|float'],
    ['$a + 1.5', 'float'],
    ['$a + []', 'array<mixed>'],
    ['[] + $a', 'array<mixed>'],
    ['[] + []', 'array<mixed>'],
    ['1 + []', 'never'],
    ['$a - 1', 'int|float'],
    ['$a / 2', 'int|float'],
    ['$a / 1.5', 'float'],
    ['$a ** 2', 'int|float'],
    ['$a ** 1.5', 'float'],
    ['$a % 2', 'int'],
    ['(int) $a * 2', 'int'],
    ['(float) $a * 2', 'float'],
    ['min((int) $a, 30) / 30', 'int|float'],
    ['1 - []', 'never'],
    ['1 * []', 'never'],
    ['1 / []', 'never'],
    ['1 % []', 'never'],
    ['1 ** []', 'never'],
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

it(
    'infers interpolated strings',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    'simple variable' => ['"hello $name"', 'string(`hello ${unknown}`)'],
    'braced variable' => ['"hello {$name}!"', 'string(`hello ${unknown}!`)'],
    'multiple variables' => ['"$first $last"', 'string(`${unknown} ${unknown}`)'],
    'adjacent variables' => ['"{$first}{$last}"', 'string(`${unknown}${unknown}`)'],
    'array access' => ['"hello {$names[0]}"', 'string(`hello ${unknown}`)'],
    'method call' => ['"price: {$foo->price()}"', 'string(`price: ${unknown}`)'],
    'escaping' => ['"foo`bar $name"', 'string(`foo\\`bar ${unknown}`)'],
    'heredoc' => ["<<<TEXT\nhello \$name!\nTEXT", 'string(`hello ${unknown}!`)'],
]);

it('collapses interpolated literal variables into a literal string', function () {
    $type = getVariableTypeAfter(<<<'PHP'
$name = 'world';
$count = 3;
$message = "hello {$name}: $count";
PHP, 'message');

    expect($type->toString())->toBe('string(hello world: 3)');
});

it('preserves the inferred types of interpolated expressions', function () {
    $type = getVariableTypeAfter(<<<'PHP'
$count = (int) $input;
$message = "count: $count!";
PHP, 'message');

    expect($type)->toBeInstanceOf(ConcatenatedStringType::class)
        ->and($type->toString())->toBe('string(`count: ${int}!`)');
});

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
