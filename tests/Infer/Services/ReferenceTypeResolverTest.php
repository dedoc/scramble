<?php

it('infers static method call type', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public static function foo ($a) {
        return $a;
    }
}
EOD)->getExpressionType("Foo::foo('wow')");

    expect($type->toString())->toBe('string(wow)');
});

it('infers static method call type with named args', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public static function foo ($a) {
        return $a;
    }
}
EOD)->getExpressionType("Foo::foo(a: 'wow')");

    expect($type->toString())->toBe('string(wow)');
});

it('infers static method call type with named unpacked args', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public static function foo ($a) {
        return $a;
    }
}
EOD)->getExpressionType("Foo::foo(...['a' => 'wow'])");

    expect($type->toString())->toBe('string(wow)');
});
