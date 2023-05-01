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
