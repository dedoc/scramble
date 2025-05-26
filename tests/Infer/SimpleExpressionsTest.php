<?php

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
    'doesnt fail on dynamic static fetch',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['Something::{$v}', 'unknown'],
]);

// @todo
// casts test (int, float, bool, string)
// array with literals test (int, float, bool, string)
