<?php

// Tests for which definition is created from class' source

use Dedoc\Scramble\Support\Type\SideEffects\SelfTemplateDefinition;

it('class generates definition', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {}
EOD)->getClassDefinition('Foo');

    expect($type->name)->toBe('Foo');
    expect($type->templateTypes)->toHaveCount(0);
    expect($type->properties)->toHaveCount(0);
    expect($type->methods)->toHaveCount(0);
});

it('adds properties and methods to class definition', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop;
    public function foo () {}
}
EOD)->getClassDefinition('Foo');

    expect($type->name)->toBe('Foo');
    expect($type->properties)->toHaveCount(1)->toHaveKey('prop');
    expect($type->methods)->toHaveCount(1)->toHaveKey('foo');
});

it('infer properties default types from values', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop = 42;
}
EOD)->getClassDefinition('Foo');

    expect($type->templateTypes)->toHaveCount(1);
    expect($type->properties['prop']->type->toString())->toBe('TProp');
    expect($type->properties['prop']->defaultType->toString())->toBe('int(42)');
});

it('infers properties types from typehints', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public int $prop;
}
EOD)->getClassDefinition('Foo');

    expect($type->templateTypes)->toHaveCount(0);
    expect($type->properties['prop']->type->toString())->toBe('int');
    expect($type->properties['prop']->defaultType)->toBeNull();
});

it('setting a parameter to property in constructor makes it template type', function () {
    $type = analyzeFile(__DIR__.'/files/class_with_simple_constructor_and_property.php')
        ->getClassDefinition('Foo');

    expect($type->templateTypes)->toHaveCount(1);
    expect($type->templateTypes[0]->toString())->toBe('TProp');
    expect($type->properties['prop']->type->toString())->toBe('TProp');
    expect($type->methods['__construct']->type->toString())->toBe('(TProp): void');
});

it('setting a parameter to property in method makes it local method template type and adds a side effect', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop;
    public function setProp($a) {
        $this->prop = $a;
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->templateTypes)->toHaveCount(1);
    expect($type->templateTypes[0]->toString())->toBe('TProp');

    expect($type->properties['prop']->type->toString())->toBe('TProp');

    expect($type->methods['setProp']->type->toString())->toBe('<TA>(TA): void');
    expect($type->methods['setProp']->sideEffects)->toHaveCount(1)
        ->and($sideEffect = $type->methods['setProp']->sideEffects[0])
        ->toBeInstanceOf(SelfTemplateDefinition::class)
        ->and($sideEffect->definedTemplate)->toBe('TProp')
        ->and($sideEffect->type->toString())->toBe('TA');
});

it('understands self type', function () {
    $type = analyzeFile(__DIR__ . '/files/class_with_method_that_returns_self.php')
        ->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())->toBe('(): self');
});

it('understands method calls type', function () {
    $type = analyzeFile(__DIR__.'/files/class_with_self_chain_calls_method.php')
        ->getClassDefinition('Foo');

    expect($type->methods['bar']->type->toString())->toBe('(): int(1)');
});

it('infers templated property fetch type', function () {
    $type = analyzeFile(__DIR__ . '/files/class_with_property_fetch_in_method.php')
        ->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())->toBe('(): TProp');
});
