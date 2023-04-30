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

$b = (new Foo)->foo('wow');
EOD)->getVarType('b');

    expect($type->toString())->toBe('string(wow)');
});
