<?php

it('tracks compound assignment mutations and expression results', function (string $expression, string $value, string $type) {
    $code = "\$a = {$value};\n\$b = {$expression};";

    expect(getVariableTypeAfter($code, 'a')->toString())->toBe($type)
        ->and(getVariableTypeAfter($code, 'b')->toString())->toBe($type);
})->with([
    'integer addition' => ['$a += 5', '0', 'int'],
    'integer subtraction' => ['$a -= 5', '10', 'int'],
    'integer multiplication' => ['$a *= 5', '2', 'int'],
    'integer division' => ['$a /= 5', '10', 'int|float'],
    'integer modulo' => ['$a %= 3', '10', 'int'],
    'integer power' => ['$a **= 3', '2', 'int|float'],
    'float addition' => ['$a += 1', '1.5', 'float'],
    'unknown addition' => ['$a += unavailable()', '0', 'int|float'],
    'array addition' => ["\$a += ['y' => 2, 'x' => 3]", "['x' => 1]", 'array{x: int(1), y: int(2)}'],
    'concatenation' => ["\$a .= '.csv'", "'export_'", 'string(export_.csv)'],
    'null coalescing' => ['$a ??= 5', 'null', 'int(5)'],
]);

it('does not fold a numeric compound assignment in a loop into a constant', function (string $operand) {
    $code = "\$step = 2 + 3;\n\$a = 0;\nforeach ([1, 2, 3] as \$item) { \$a += {$operand}; }";

    expect(getVariableTypeAfter($code, 'a')->toString())->toBe('int');
})->with([
    'literal' => ['1'],
    'variable holding a literal' => ['$step'],
    'union of literals' => ['$item ? 1 : 2'],
    'boolean' => ['true'],
]);
