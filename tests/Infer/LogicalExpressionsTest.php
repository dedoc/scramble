<?php

it('infers logical operations using PHP truthiness', function (string $operator, string $left, string $right, string $and, string $or, string $xor) {
    $expected = match ($operator) {
        '&&', 'and' => $and,
        '||', 'or' => $or,
        'xor' => $xor,
    };

    expect(getStatementType("(($left) $operator ($right))")->toString())->toBe($expected);
})->with(['&&', '||', 'and', 'or', 'xor'])->with([
    'true and true' => ['true', 'true', 'boolean(true)', 'boolean(true)', 'boolean(false)'],
    'true and false' => ['true', 'false', 'boolean(false)', 'boolean(true)', 'boolean(true)'],
    'false and true' => ['false', 'true', 'boolean(false)', 'boolean(true)', 'boolean(true)'],
    'false and false' => ['false', 'false', 'boolean(false)', 'boolean(false)', 'boolean(false)'],
    'unknown and unknown' => ['$leftInput', '$rightInput', 'boolean', 'boolean', 'boolean'],
    'false and unknown' => ['false', '$leftInput', 'boolean(false)', 'boolean', 'boolean'],
    'unknown and false' => ['$leftInput', 'false', 'boolean(false)', 'boolean', 'boolean'],
    'true and unknown' => ['true', '$leftInput', 'boolean', 'boolean(true)', 'boolean'],
    'unknown and true' => ['$leftInput', 'true', 'boolean', 'boolean(true)', 'boolean'],
    'zero and null' => ['0', 'null', 'boolean(false)', 'boolean(false)', 'boolean(false)'],
    'nonzero integer and float' => ['1', '1.5', 'boolean(true)', 'boolean(true)', 'boolean(false)'],
    'zero float and empty string' => ['0.0', "''", 'boolean(false)', 'boolean(false)', 'boolean(false)'],
    'zero string and nonempty string' => ["'0'", "'false'", 'boolean(false)', 'boolean(true)', 'boolean(true)'],
    'nonzero numeric string and unknown' => ["'0.0'", '$leftInput', 'boolean', 'boolean(true)', 'boolean'],
    'empty and nonempty arrays' => ['[]', '[null]', 'boolean(false)', 'boolean(true)', 'boolean(true)'],
    'truthy union' => ["(\$condition ? 'a' : 'b')", 'true', 'boolean(true)', 'boolean(true)', 'boolean(false)'],
    'mixed truthiness union' => ['($condition ? true : false)', 'true', 'boolean', 'boolean(true)', 'boolean'],
]);

it('respects logical operator precedence', function (string $expression, string $expected) {
    expect(getStatementType("($expression)")->toString())->toBe($expected);
})->with([
    ['true || false && false', 'boolean(true)'],
    ['(true || false) && false', 'boolean(false)'],
    ['true or false and false', 'boolean(true)'],
    ['true xor true and false', 'boolean(true)'],
    ['true or true xor true', 'boolean(true)'],
]);

it('evaluates logical operations after resolving calls', function () {
    expect(getStatementType('(new LogicalExpressionsTestSubject)->disabled() || false')->toString())
        ->toBe('boolean(false)');
});

it('preserves boolean fallback for typed parameters', function () {
    $type = getStatementType('(fn (bool $a, bool $b) => $a && $b)((bool) $leftInput, (bool) $rightInput)');

    expect($type->toString())->toBe('boolean');
});

it('evaluates logical operations with literal call-site arguments', function (string $operator, string $expected) {
    $type = getStatementType("(fn (bool \$left, bool \$right) => (\$left $operator \$right))(true, false)");

    expect($type->toString())->toBe($expected);
})->with([
    ['&&', 'boolean(false)'],
    ['and', 'boolean(false)'],
    ['||', 'boolean(true)'],
    ['or', 'boolean(true)'],
    ['xor', 'boolean(true)'],
]);

class LogicalExpressionsTestSubject
{
    public function disabled(): bool
    {
        return false;
    }
}
