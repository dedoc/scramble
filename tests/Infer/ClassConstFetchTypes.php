<?php

it(
    'infers class const fetch types',
    fn ($statement, $expectedType) => expect(getStatementType($statement)->toString())->toBe($expectedType),
)->with([
    ['$var::class', 'string'],
    ['(new SomeType)::class', 'string(SomeType)'],
    ['SomeType::class', 'string(SomeType)'],
]);
