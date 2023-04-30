<?php

// Tests for resolving references behavior

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\UnknownType;

it('supports creating an object without constructor', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop;
}
EOD
    )->getExpressionType('new Foo()');

    expect($type)->toBeInstanceOf(Generic::class)
        ->and($type->name)->toBe('Foo')
        ->and($type->toString())->toBe('Foo<unknown>')
        ->and($type->templateTypesMap)->toHaveKeys(['TProp'])
        ->and($type->templateTypesMap['TProp'])->toBeInstanceOf(UnknownType::class);
});

it('supports creating an object with a constructor', function () {
    $type = analyzeFile(__DIR__.'/files/class_with_simple_constructor_and_property.php')
        ->getExpressionType('new Foo(132)');

    expect($type)->toBeInstanceOf(Generic::class)
        ->and($type->name)->toBe('Foo')
        ->and($type->templateTypesMap)->toHaveKeys(['TProp'])
        ->and($type->templateTypesMap['TProp']->toString())->toBe('int(132)')
        ->and($type->toString())->toBe('Foo<int(132)>');
});

it('self template definition side effect works', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop;
    public function setProp($a) {
        $this->prop = $a;
        return $this;
    }
}
EOD)->getExpressionType('(new Foo)->setProp(123)');

    expect($type->toString())->toBe('Foo<int(123)>');
});

it('evaluates self type', function () {
    $type = analyzeFile(__DIR__ . '/files/class_with_method_that_returns_self.php')
        ->getExpressionType('(new Foo)->foo()');

    expect($type->toString())->toBe('Foo');
});

it('understands method calls type', function () {
    $type = analyzeFile(__DIR__.'/files/class_with_self_chain_calls_method.php')
        ->getExpressionType('(new Foo)->foo()->foo()->one()');

    expect($type->toString())->toBe('int(1)');
});

it('understands templated property fetch type value for property fetch', function () {
    $type = analyzeFile(__DIR__ . '/files/class_with_property_fetch_in_method.php')
        ->getExpressionType('(new Foo(42))->prop');

    expect($type->toString())->toBe('int(42)');
});

it('understands templated property fetch type value for property fetch called in method', function () {
    $type = analyzeFile(__DIR__ . '/files/class_with_property_fetch_in_method.php')
        ->getExpressionType('(new Foo(42))->foo()');

    expect($type->toString())->toBe('int(42)');
});

