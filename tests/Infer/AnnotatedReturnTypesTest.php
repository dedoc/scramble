<?php

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
