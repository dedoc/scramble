<?php

it(
    'infers simple types',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['null', 'null'],
    ['true', 'boolean(true)'],
    ['false', 'boolean(false)'],
]);

it(
    'infers boolean operations',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['! $some', 'boolean'],
    ['!! $some', 'boolean'],
]);

// @todo
// casts test (int, float, bool, string)
// array with literals test (int, float, bool, string)
