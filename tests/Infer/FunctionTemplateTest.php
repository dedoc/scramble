<?php

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
