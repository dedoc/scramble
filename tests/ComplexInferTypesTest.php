<?php

it('infers param type', function () {
    $code = <<<'EOD'
<?php
function foo (int $a = 4) {
    return $a;
}
EOD;

    $result = analyzeFile($code);

    expect($result->getFunctionDefinition('foo')->type->getReturnType()->toString())->toBe('TA');
});

it('infers type from assignment', function () {
    $code = <<<'EOD'
<?php
$a = 2;
$a = 5;
EOD;

    $result = analyzeFile($code);

    expect($result->getVarType('a')->toString())->toBe('int(5)');
})->skip('implement var type testing way');

it('assignment works with closure scopes', function () {
    $code = <<<'EOD'
<?php
$a = 2;
$b = fn () => $a;
EOD;

    $result = analyzeFile($code);

    expect($result->getVarType('b')->toString())->toBe('(): int(2)');
})->skip('implement var type testing way');

it('assignment works with fn scope', function () {
    $code = <<<'EOD'
<?php
$a = 2;
$b = function () use ($a) {
    return $a;
};
EOD;

    $result = analyzeFile($code);

    expect($result->getVarType('b')->toString())->toBe('(): int(2)');
})->skip('implement var type testing way');

it('array type is analyzed with details', function () {
    $code = <<<'EOD'
<?php
class Foo {
    public function toArray(): array
    {
        return ['foo' => 'bar'];
    }
}
EOD;

    $result = analyzeFile($code);

    expect($result->getClassDefinition('Foo')->getMethodCallType('toArray')->toString())
        ->toBe('array{foo: string(bar)}');
});

/*
 * When int, float, bool, return type annotated, there is no point in using types from return
 * as there is no more useful information about the function can be extracted.
 * Sure we could've extracted some literals, but for now there is no point (?).
 */
it('uses function return annotation type when int, float, bool, used', function () {
    $code = <<<'EOD'
<?php
class Foo {
    public function bar(): int
    {
        return [];
    }
}
EOD;

    $result = analyzeFile($code);

    expect($result->getClassDefinition('Foo')->getMethodCallType('bar')->toString())
        ->toBe('int');
});

it('infers class fetch type (#917)', function () {
    $code = <<<'EOD'
<?php
function foo (string $class) {
    return (new $class)::class;
}
EOD;

    $result = analyzeFile($code);

    expect($result->getFunctionDefinition('foo')->type->getReturnType()->toString())->toBe('string');
});

it('infers class fetch type (#912)', function () {
    $code = <<<'EOD'
<?php
function bar (): mixed {
    return unknown();
}
function foo () {
    return bar()->sample();
}
EOD;

    $result = analyzeFile($code);

    expect($result->getFunctionDefinition('foo')->type->getReturnType()->toString())->toBe('unknown');
});
