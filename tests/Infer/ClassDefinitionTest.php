<?php

// Tests for which definition is created from class' source

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypePath;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function () {
    $this->index = app(Index::class);

    $this->classAnalyzer = new ClassAnalyzer($this->index);
});

it('finds type', function () {
    $type = getStatementType(<<<'EOD'
['a' => fn (int $b) => 123]
EOD);

    $path = TypePath::findFirst(
        $type,
        fn ($t) => $t instanceof LiteralIntegerType,
    );

    expect($path?->getFrom($type)->toString())->toBe('int(123)');
})->todo('move to its own test case');

it('infers from property default type', function () {
    Scramble::registerExtension(AfterFoo_ClassDefinitionTest::class);

    $this->classAnalyzer->analyze(Foo_ClassDefinitionTest::class);

    expect(getStatementType('new '.Foo_ClassDefinitionTest::class)->toString())
        ->toBe('Foo_ClassDefinitionTest<Illuminate\Database\Eloquent\Builder>');
});
class Foo_ClassDefinitionTest
{
    public $prop = Builder::class;
}
class AfterFoo_ClassDefinitionTest implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === Foo_ClassDefinitionTest::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event)
    {
        $event->classDefinition->templateTypes = [
            $t = new TemplateType('T'),
        ];
        $event->classDefinition->properties['prop'] = new ClassPropertyDefinition(
            type: new GenericClassStringType($t),
            defaultType: new GenericClassStringType(new ObjectType(Builder::class)),
        );
    }
}

it('infers from constructor argument type', function () {
    Scramble::registerExtension(AfterBar_ClassDefinitionTest::class);

    $this->classAnalyzer->analyze(Bar_ClassDefinitionTest::class);

    expect(getStatementType('new '.Bar_ClassDefinitionTest::class.'(prop: '.\Dedoc\Scramble\Support\Generator\Schema::class.'::class)')->toString())
        ->toBe('Bar_ClassDefinitionTest<Dedoc\Scramble\Support\Generator\Schema>');
});
class Bar_ClassDefinitionTest
{
    public function __construct(public $prop = Builder::class) {}
}
class AfterBar_ClassDefinitionTest implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === Bar_ClassDefinitionTest::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event)
    {
        $event->classDefinition->templateTypes = [
            $t = new TemplateType('T'),
        ];
        $event->classDefinition->properties['prop'] = new ClassPropertyDefinition(
            type: new GenericClassStringType($t),
            defaultType: new GenericClassStringType(new ObjectType(Builder::class)),
        );
    }
}

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

it('infers properties types from typehints', function ($paramType, $expectedParamType, $expectedTemplateDefinitionType = '') {
    $def = analyzeFile("<?php class Foo { public $paramType \$a; }")->getClassDefinition('Foo');

    expect($def->properties['a']->type->toString())->toBe($expectedParamType);

    if (! $expectedTemplateDefinitionType) {
        expect($def->templateTypes)->toBeEmpty();
    } else {
        expect($def->templateTypes[0]->toDefinitionString())->toBe($expectedTemplateDefinitionType);
    }
})->with('extendableTemplateTypes');

it('setting a parameter to property in constructor makes it template type', function () {
    $type = analyzeFile(__DIR__.'/files/class_with_simple_constructor_and_property.php')
        ->getClassDefinition('Foo');

    expect($type->templateTypes)->toHaveCount(1);
    expect($type->templateTypes[0]->toString())->toBe('TProp');
    expect($type->properties['prop']->type->toString())->toBe('TProp');
    expect($type->methods['__construct']->type->toString())->toBe('(TProp): void');
});

it('setting a parameter to property in method makes it local method template type and defines self out', function () {
    $def = $this->classAnalyzer->analyze(SetPropToMethod_ClassDefinitionTest::class);

    expect($def->templateTypes)->toHaveCount(1)
        ->and($def->templateTypes[0]->toString())->toBe('TProp');

    expect($def->properties['prop']->type->toString())->toBe('TProp');

    $setProp = $def->getMethodDefinition('setProp');

    expect($setProp->type->toString())->toBe('<TA>(TA): void')
        ->and($setProp->getSelfOutType()->toString())->toBe('self<TA>');
});
class SetPropToMethod_ClassDefinitionTest
{
    public $prop;

    public function setProp($a)
    {
        $this->prop = $a;
    }
}

it('understands self type', function () {
    $type = analyzeFile(__DIR__.'/files/class_with_method_that_returns_self.php')
        ->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())->toBe('(): self');
});

it('understands method calls type', function () {
    $type = analyzeFile(__DIR__.'/files/class_with_self_chain_calls_method.php')
        ->getClassDefinition('Foo');

    expect($type->methods['bar']->type->toString())->toBe('(): int(1)');
});

it('infers templated property fetch type', function () {
    $type = analyzeFile(__DIR__.'/files/class_with_property_fetch_in_method.php')
        ->getClassDefinition('Foo');

    expect($type->methods['foo']->type->toString())->toBe('(): TProp');
});

it('generates template types without conflicts', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public $prop;
    public function getPropGetter($prop) {
        return fn ($prop, $q) => [$q, $prop, $this->prop];
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->methods['getPropGetter']->type->toString())
        ->toBe('<TProp1>(TProp1): <TProp2, TQ>(TProp2, TQ): list{TQ, TProp2, TProp}');
});

it('generates definition for inheritance', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo extends Bar {
}
class Bar {
}
EOD)->getClassDefinition('Foo');

    expect($type->parentFqn)->toBe('Bar');
});

it('generates definition based on parent when analyzing inheritance', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo extends Bar {
    public function foo () {
        return $this->barProp;
    }
}
class Bar {
    public $barProp;
    public function __construct($b) {
        $this->barProp = $b;
    }
}
EOD)->getClassDefinition('Foo');

    expect($type->parentFqn)->toBe('Bar');
});
