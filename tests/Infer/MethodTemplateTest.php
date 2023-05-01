<?php

it('generates function type with generic correctly', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo ($a) {
        return $a;
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())->toBe('<TA>(TA): TA');
});

it('gets a type of call of a function with generic correctly', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo ($a) {
        return $a;
    }
}
EOD)->getExpressionType("(new Foo)->foo('wow')");

    expect($type->toString())->toBe('string(wow)');
});
