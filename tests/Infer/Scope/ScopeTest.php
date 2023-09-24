<?php

function getStatementTypeForScopeTest(string $statement, array $extensions = [])
{
    return analyzeFile('<?php', $extensions)->getExpressionType($statement);
}

it('infers property fetch nodes types', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['$foo->bar', 'unknown'],
    ['$foo->bar->{"baz"}', 'unknown'],
]);

it('infers concat string type', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['"a"."b"."c"', 'string(string(a), string(b), string(c))'],
]);
it('infers concat string type with unknowns', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['"a"."b".auth()->user()->id', 'string(string(a), string(b), unknown)'],
]);
