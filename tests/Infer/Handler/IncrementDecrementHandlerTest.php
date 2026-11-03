<?php

it('tracks increment and decrement mutations and expression results', function (string $expression, string $value, string $variableType, string $resultType) {
    $code = "\$a = {$value};\n\$b = {$expression};";

    expect(getVariableTypeAfter($code, 'a')->toString())->toBe($variableType)
        ->and(getVariableTypeAfter($code, 'b')->toString())->toBe($resultType);
})->with([
    'integer pre-increment' => ['++$a', '0', 'int', 'int'],
    'integer post-increment' => ['$a++', '0', 'int', 'int(0)'],
    'integer pre-decrement' => ['--$a', '0', 'int', 'int'],
    'integer post-decrement' => ['$a--', '0', 'int', 'int(0)'],
    'float pre-increment' => ['++$a', '1.5', 'float', 'float'],
    'float post-increment' => ['$a++', '1.5', 'float', 'float(1.5)'],
    'float pre-decrement' => ['--$a', '1.5', 'float', 'float'],
    'float post-decrement' => ['$a--', '1.5', 'float', 'float(1.5)'],
    'unknown pre-increment' => ['++$a', 'unavailable()', 'int|float', 'int|float'],
    'unknown post-increment' => ['$a++', 'unavailable()', 'int|float', 'unknown'],
    'unknown pre-decrement' => ['--$a', 'unavailable()', 'int|float', 'int|float'],
    'unknown post-decrement' => ['$a--', 'unavailable()', 'int|float', 'unknown'],
]);
