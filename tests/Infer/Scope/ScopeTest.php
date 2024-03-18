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

it('infers ternary expressions nodes types', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['unknown() ? 1 : null', 'int(1)|null'],
    ['unknown() ? 1 : 1', 'int(1)'],
]);
