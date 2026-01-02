<?php

use Dedoc\Scramble\Infer\Scope\TypeEffect;

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
    ['unknown() ?: 1', 'unknown|int(1)'],
    ['(int) unknown() ?: "w"', 'int|string(w)'],
    ['1 ?: 1', 'int(1)'],
    ['unknown() ? 1 : unknown()', 'int(1)|unknown'],
    ['unknown() ? unknown() : unknown()', 'unknown'],
    ['unknown() ?: unknown()', 'unknown'],
    ['unknown() ?: true ?: 1', 'unknown|boolean(true)|int(1)'],
    ['unknown() ?: unknown() ?: unknown()', 'unknown'],
]);

it('infers expressions from a null coalescing operator', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['unknown() ?? 1', 'unknown|int(1)'],
    ['(int) unknown() ?? "w"', 'int|string(w)'],
    ['1 ?? 1', 'int(1)'],
    ['unknown() ?? unknown()', 'unknown'],
    ['unknown() ?? true ?? 1', 'unknown|boolean(true)|int(1)'],
    ['unknown() ?? unknown() ?? unknown()', 'unknown'],
]);

it('infers match node type', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    [<<<'EOD'
match (unknown()) {
    42 => 1,
    default => null,
}
EOD, 'int(1)|null'],
]);

it('infers throw node type', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['throw new Exception("foo")', 'void'],
]);

it('infers var var type', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['$$a', 'unknown'],
]);

it('infers array type with const fetch keys', function ($code, $expectedTypeString) {
    expect(getStatementTypeForScopeTest($code)->toString())->toBe($expectedTypeString);
})->with([
    ['['.Foo_ScopeTest::class.'::FOO => 42]', 'array{foo: int(42)}'],
]);
class Foo_ScopeTest
{
    const FOO = 'foo';
}

/**
 * Imagine a function:
 * function foo ($a) {
 *     return match ($a) {
 *         'foo' => 1,
 *         'bar' => 42,
 *         default => null,
 *     }
 * }
 * The return type of the function is 1|42|null.
 *
 * The test here is testing the part of the functionality that allows to know that when
 * return type is specifically 42, `$a` variable must have 'bar' type.
 */
it('allows inspecting known facts about variables based on returned type', function () {
    $code = <<<'EOF'
<?php
function foo ($a) {
     return match ($a) {
         'foo' => 1,
         'bar' => 42,
         default => null,
     };
}
EOF;

    $scope = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getScope();

    $barConditions = $scope->typeEffects
        ->filter(fn (TypeEffect $re) => $re->type && $re->facts->count() === 1)
        ->all();

    expect($barConditions)->toHaveCount(2);
});

it('allows inspecting known facts about variables based on if', function () {
    $code = <<<'EOF'
<?php
function foo ($a) {
    if ($a === 'foo') {
        return 1;
    }

    if ($a === 'bar') {
        return 42;
    }

    return null;
}
EOF;

    $scope = analyzeFile($code)
        ->getFunctionDefinition('foo')
        ->getScope();

    $barConditions = $scope->typeEffects
        ->filter(fn (TypeEffect $re) => $re->type && $re->facts->count() === 1)
        ->all();

    expect($barConditions)->toHaveCount(2);
});
