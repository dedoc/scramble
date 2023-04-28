<?php

// Definition test

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\SideEffects\SelfTemplateDefinition;
use Dedoc\Scramble\Support\Type\UnknownType;

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
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop;
    public function __construct($a) {
        $this->prop = $a;
    }
}
EOD)->getClassDefinition('Foo');

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

it('supports creating an object without constructor', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop;
}
$a = new Foo();
EOD
    )->getVarType('a');
    expect($type)->toBeInstanceOf(Generic::class)
        ->and($type->name)->toBe('Foo')
        ->and($type->toString())->toBe('Foo<unknown>')
        ->and($type->templateTypesMap)->toHaveKeys(['TProp'])
        ->and($type->templateTypesMap['TProp'])->toBeInstanceOf(UnknownType::class);
});

it('supports creating an object with a constructor', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop;
    public function __construct($a) {
        $this->prop = $a;
    }
}
$a = new Foo(132);
EOD
    )->getVarType('a');

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
$a = (new Foo)->setProp(123);
EOD)->getVarType('a');

    expect($type->toString())->toBe('Foo<int(123)>');
});

it('understands this type', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo () {
        return $this;
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())->toBe('(): self');
});



// Not definition test

it('evaluates this type', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo () {
        return $this;
    }
}
$a = (new Foo)->foo();
EOD)->getVarType('a');

    expect($type->toString())->toBe('Foo');
});

return;

it('property assignment defines a template for class', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop;
    public function foo ($a) {
        $this->prop = $a;
        return $this;
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->name)->toBe('Foo');

    expect($type->templateTypes)->toHaveCount(1);
    expect($type->templateTypes[0]->toString())->toBe('TProp');

    expect($type->properties['prop']->toString())->toBe('TProp');

    expect($type->methods['foo']->toString())->toBe('(TProp): Foo<TProp>');
});

it('track property assignment to in method', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop;
    public function foo ($a) {
        $this->prop = $a;
        return $this;
    }
}

$a = (new Foo)->foo(123);
EOD)->getVarType('a');

    expect($type->getPropertyFetchType('prop')->toString())->toBe('int(123)');
});
