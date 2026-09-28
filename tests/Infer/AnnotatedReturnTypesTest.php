<?php

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;

it('stamps a closure declared return without selecting over inferred during the ast pass', function (string $expression, ?string $declared, string $inferred) {
    $type = getUnresolvedStatementType($expression);

    expect($type)->toBeInstanceOf(FunctionType::class)
        ->and($type->declaredReturnType?->toString())->toBe($declared)
        ->and($type->getReturnType()->toString())->toBe($inferred);
})->with([
    'typed arrow function' => ["fn (): string => 'post-sqid'", 'string', 'string(post-sqid)'],
    'typed closure' => ["function (): ?string { return 'post-sqid'; }", 'null|string', 'string(post-sqid)'],
    'declared array with inferred empty list' => ['fn (): array => []', 'array<mixed>', 'list{}'],
    'untyped arrow function' => ["fn () => 'post-sqid'", null, 'string(post-sqid)'],
]);

it('selects a closure return when the function type is resolved', function (string $expression, string $selected) {
    $type = getUnresolvedStatementType($expression);

    $resolved = ReferenceTypeResolver::getInstance()->resolve(new GlobalScope, $type);

    expect($resolved)->toBeInstanceOf(FunctionType::class)
        ->and($resolved->getReturnType()->toString())->toBe($selected);
})->with([
    'typed arrow function keeps a compatible inferred literal' => ["fn (): string => 'post-sqid'", 'string(post-sqid)'],
    'declared array keeps a compatible inferred list' => ['fn (): array => []', 'list{}'],
    'declared array wins over an incompatible inferred type' => ['fn (): array => unk()', 'array<mixed>'],
    'untyped arrow function uses the inferred return' => ["fn () => 'post-sqid'", 'string(post-sqid)'],
]);

it('stamps a declared getter return inside an unresolved Attribute call', function () {
    $call = getUnresolvedStatementType("\\Illuminate\\Database\\Eloquent\\Casts\\Attribute::make(get: fn (): string => 'post-sqid')");

    expect($call)->toBeInstanceOf(StaticMethodCallReferenceType::class)
        ->and($getter = $call->arguments['get'])->toBeInstanceOf(FunctionType::class)
        ->and($getter->declaredReturnType->toString())->toBe('string')
        ->and($getter->getReturnType()->toString())->toBe('string(post-sqid)');
});

it('binds a closure static declared return to the lexical class', function () {
    $return = analyzeFile(<<<'EOD'
<?php
class ClosureStatic_AnnotatedReturnTypesTest
{
    public function fn()
    {
        return fn (): static => $this;
    }
}
EOD)->getClassDefinition('ClosureStatic_AnnotatedReturnTypesTest')
        ->getMethodDefinition('fn')
        ->getInferredReturnType();

    expect($return)->toBeInstanceOf(FunctionType::class)
        ->and($return->declaredReturnType?->toString())->toBe('self')
        ->and($return->getReturnType()->toString())->toBe('self');
});

it('generates function type with generic correctly', function (string $returnAnnotation, string $returnExpression, string $expectedInferredReturnTypeString) {
    $definition = analyzeFile(<<<"EOD"
<?php
function foo (): {$returnAnnotation} {
    return {$returnExpression};
}
EOD)->getFunctionDefinition('foo');

    expect($definition->getReturnType()->toString())->toBe($expectedInferredReturnTypeString);
})->with([
    ['Foo_AnnotatedReturnTypesTest', 'new Foo_AnnotatedReturnTypesTest(42)', 'Foo_AnnotatedReturnTypesTest<int(42)>'],
    ['int', 'new Foo_AnnotatedReturnTypesTest(42)', 'int'],
    ['Foo_AnnotatedReturnTypesTest', '42', 'Foo_AnnotatedReturnTypesTest'],
]);
class Foo_AnnotatedReturnTypesTest
{
    public function __construct(private int $wow) {}
}

it('understands static keywords annotations', function () {
    $type = getStatementType('(new Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\AnnotatedBar)->fooMethod()->build()');

    expect($type->toString())->toBe('array{from: string(bar)}');
});
