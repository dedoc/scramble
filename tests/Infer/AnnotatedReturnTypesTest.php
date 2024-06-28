<?php

it('generates function type with generic correctly', function (string $returnAnnotation, string $returnExpression, string $inferredReturnTypeString) {
    $type = analyzeFile(<<<"EOD"
<?php
function foo (): {$returnAnnotation} {
    return {$returnExpression};
}
EOD)->getFunctionDefinition('foo');

    expect($type->type->returnType->toString())->toBe($inferredReturnTypeString);
})->with([
    ['Foo_AnnotatedReturnTypesTest', 'new Foo_AnnotatedReturnTypesTest(42)', 'Foo_AnnotatedReturnTypesTest<int(42)>'],
    ['int', 'new Foo_AnnotatedReturnTypesTest(42)', 'int'],
    ['Foo_AnnotatedReturnTypesTest', '42', 'Foo_AnnotatedReturnTypesTest'],
]);

class Foo_AnnotatedReturnTypesTest {
    public function __construct(private int $wow){}
}
