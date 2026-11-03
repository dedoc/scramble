<?php

use Dedoc\Scramble\Support\Type\ConditionalType;

it('generates function type with generic correctly', function () {
    $type = analyzeFile(<<<'EOD'
<?php
function foo ($a) {
    return $a;
}
EOD)->getFunctionDefinition('foo');

    expect($type->type->toString())->toBe('<TA>(TA): TA');
});

it('gets a type of call of a function with generic correctly', function () {
    $type = analyzeFile(<<<'EOD'
<?php
function foo ($a) {
    return $a;
}
EOD)->getExpressionType("foo('wow')");

    expect($type->toString())->toBe('string(wow)');
});

it('infers a literal array key concatenated with a function argument', function (string $key) {
    $type = analyzeFile(<<<PHP
<?php
function foo(string \$key) {
    return [
        $key => 42,
    ];
}
PHP)->getExpressionType("foo('wow')");

    expect($type->toString())->toBe('array{strwow: int(42)}');
})->with([
    '\'str\'.$key',
    '"str{$key}"',
    '"str$key"',
]);

it('preserves a conditional return type for a direct argument condition', function () {
    $definition = analyzeFile(<<<'PHP'
<?php
function foo(bool $enabled) {
    return $enabled ? 'yes' : 0;
}
PHP)->getFunctionDefinition('foo');

    expect($definition->type->getReturnType())
        ->toBeInstanceOf(ConditionalType::class);
});

it('preserves conditional inference with a declared return type', function (string $returnType) {
    $analysis = analyzeFile(<<<PHP
<?php
function foo(bool \$enabled): $returnType {
    return \$enabled ? 'yes' : 'no';
}
PHP);

    expect($analysis->getExpressionType('foo(true)')->toString())->toBe('string(yes)')
        ->and($analysis->getExpressionType('foo(false)')->toString())->toBe('string(no)');
})->with(['string', 'string|int']);

it('preserves a union containing a conditional type with a declared string return type', function (string $ready, string $expectedStatus) {
    $analysis = analyzeFile(<<<'PHP'
<?php
function foo(bool $ready, $finalized): string {
    if ($finalized->done) {
        return 'finalized';
    }

    return $ready ? 'draft_ready' : 'not_ready';
}
PHP);

    expect(explode('|', $analysis->getExpressionType("foo($ready, \$unknown)")->toString()))
        ->toEqualCanonicalizing(['string(finalized)', "string($expectedStatus)"]);
})->with([
    'ready' => ['true', 'draft_ready'],
    'not ready' => ['false', 'not_ready'],
]);

it('resolves nested direct argument ternaries in methods', function () {
    $analysis = analyzeFile(<<<'PHP'
<?php
class Foo {
    public function choose($first, $second) {
        return $first ? ($second ? 'both' : 'first') : 'neither';
    }
}
PHP);

    expect($analysis->getExpressionType('(new Foo)->choose(true, true)')->toString())->toBe('string(both)')
        ->and($analysis->getExpressionType('(new Foo)->choose(true, false)')->toString())->toBe('string(first)')
        ->and($analysis->getExpressionType('(new Foo)->choose(false, true)')->toString())->toBe('string(neither)');
});

it('resolves direct argument ternaries using PHP truthiness', function (string $argument, string $expected) {
    $analysis = analyzeFile(<<<'PHP'
<?php
function foo($enabled = false) {
    return $enabled ? 'yes' : 0;
}
PHP);

    expect($analysis->getExpressionType("foo($argument)")->toString())->toBe($expected);
})->with([
    ['true', 'string(yes)'],
    ['false', 'int(0)'],
    ['', 'int(0)'],
    ['enabled: true', 'string(yes)'],
    ['1', 'string(yes)'],
    ['0', 'int(0)'],
    ['"value"', 'string(yes)'],
    ['"0"', 'int(0)'],
    ['""', 'int(0)'],
    ['null', 'int(0)'],
    ['[]', 'int(0)'],
    ['[1]', 'string(yes)'],
    ['(bool) $unknown', 'string(yes)|int(0)'],
]);

it('resolves shorthand argument ternaries', function (string $argument, string $expected) {
    $analysis = analyzeFile(<<<'PHP'
<?php
function foo($value) {
    return $value ?: 'fallback';
}
PHP);

    expect($analysis->getExpressionType("foo($argument)")->toString())->toBe($expected);
})->with([
    ['"value"', 'string(value)'],
    ['false', 'string(fallback)'],
]);

it('keeps union inference for other ternary conditions', function (string $body) {
    $definition = analyzeFile('<?php function foo($enabled) { '.$body.' }')->getFunctionDefinition('foo');

    expect($definition->type->getReturnType()->toString())->toBe('string(yes)|int(0)');
})->with([
    ['return !$enabled ? "yes" : 0;'],
    ['$local = true; return $local ? "yes" : 0;'],
    ['$enabled = false; return $enabled ? "yes" : 0;'],
]);

it('adds a type constraint onto template type for some types', function ($paramType, $expectedParamType, $expectedTemplateDefinitionType = '') {
    $def = analyzeFile("<?php function foo ($paramType \$a) {}")->getFunctionDefinition('foo');

    expect($def->type->arguments['a']->toString())->toBe($expectedParamType);

    if (! $expectedTemplateDefinitionType) {
        expect($def->type->templates)->toBeEmpty();
    } else {
        expect($def->type->templates[0]->toDefinitionString())->toBe($expectedTemplateDefinitionType);
    }
})->with('extendableTemplateTypes');

it('infers a return type of call of a function with argument default const', function () {
    $type = analyzeFile(<<<'EOD'
<?php
function foo (int $a = \Illuminate\Http\Response::HTTP_CREATED) {
    return ['a' => $a];
}
EOD)->getExpressionType('foo()');

    expect($type->toString())->toBe('array{a: int(201)}');
});
