<?php

use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;

it('preserves a closure declared return type alongside its inferred return', function (string $expression, ?string $declared, string $inferred) {
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

it('preserves a declared getter return type inside an inferred Attribute call', function () {
    $call = getUnresolvedStatementType("\\Illuminate\\Database\\Eloquent\\Casts\\Attribute::make(get: fn (): string => 'post-sqid')");

    expect($call)->toBeInstanceOf(StaticMethodCallReferenceType::class)
        ->and($getter = $call->arguments['get'])->toBeInstanceOf(FunctionType::class)
        ->and($getter->declaredReturnType->toString())->toBe('string')
        ->and($getter->getReturnType()->toString())->toBe('string(post-sqid)');
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
