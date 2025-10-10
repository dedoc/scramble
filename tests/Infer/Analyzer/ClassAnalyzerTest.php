<?php

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Tests\Infer\stubs\Child;
use Dedoc\Scramble\Tests\Infer\stubs\ChildParentSetterCalls;
use Dedoc\Scramble\Tests\Infer\stubs\ChildPromotion;
use Dedoc\Scramble\Tests\Infer\stubs\DeepChild;
use Dedoc\Scramble\Tests\Infer\stubs\Foo;
use Dedoc\Scramble\Tests\Infer\stubs\FooWithDefaultProperties;

beforeEach(function () {
    $this->index = app(Index::class);

    $this->classAnalyzer = new ClassAnalyzer($this->index);

    $this->resolver = new ReferenceTypeResolver($this->index);
});

it('resolves function return type after explicitly requested', function () {
    $fooDef = $this->classAnalyzer
        ->analyze(Foo::class)
        ->getMethodDefinition('bar');

    expect($fooDef->type->getReturnType()->toString())->toBe('int(243)');
});

it('resolves fully qualified names', function () {
    $fqnDef = $this->classAnalyzer
        ->analyze(Foo::class)
        ->getMethodDefinition('fqn');

    expect($fqnDef->type->getReturnType()->toString())->toBe('class-string<'.Foo::class.'>');
});

it('describes constructor arguments assignments as self out type on constructor', function () {
    $constructor = $this->classAnalyzer
        ->analyze(ConstructorArgumentAssignment_ClassAnalyzerTest::class)
        ->getMethodDefinition('__construct');

    expect($constructor->selfOutTypeBuilder)
        ->not->toBeNull()
        ->and($constructor->getSelfOutType()->toString())
        ->toBe('self<array{a: TFoo1}, _>');
});
class ConstructorArgumentAssignment_ClassAnalyzerTest
{
    public $foo;

    public $bar;

    public function __construct(int $foo)
    {
        $this->foo = ['a' => $foo];
    }
}

/**
 * The idea here is that any direct argument assignment of constructor argument is represented
 * as `_` in selfOutType. It is later gets ignored when self out is processed and instead is handled
 * with default handling mechanism (for now).
 */
it('describes direct constructor arguments assignments as template placeholders', function () {
    $constructor = $this->classAnalyzer
        ->analyze(ConstructorDirectArgumentAssignment_ClassAnalyzerTest::class)
        ->getMethodDefinition('__construct');

    expect($constructor->selfOutTypeBuilder)
        ->not->toBeNull()
        ->and($constructor->getSelfOutType()->toString())
        ->toBe('self<_>');
});
class ConstructorDirectArgumentAssignment_ClassAnalyzerTest
{
    public $foo;

    public function __construct(int $foo)
    {
        $this->foo = $foo;
    }
}

it('describes arguments assignments as self out type on any method', function () {
    $method = $this->classAnalyzer
        ->analyze(MethodArgumentAssignment_ClassAnalyzerTest::class)
        ->getMethodDefinition('setFoo');

    expect($method->selfOutTypeBuilder)
        ->not->toBeNull()
        ->and($method->getSelfOutType()->toString())
        ->toBe('self<array{foo: TSomething}>');
});
class MethodArgumentAssignment_ClassAnalyzerTest
{
    public $foo;

    public function setFoo(int $something)
    {
        $this->foo = ['foo' => $something];
    }
}

it('describes arguments passed to parent constructor call as part of self out type on a constructor', function () {
    $constructor = $this->classAnalyzer
        ->analyze(ParentConstructorCall_ClassAnalyzerTest::class)
        ->getMethodDefinition('__construct');

    expect($constructor->selfOutTypeBuilder)
        ->not->toBeNull()
        ->and($constructor->getSelfOutType()->toString())
        ->toBe('self<array{a: int(42)}, _>');
});
class ParentConstructorCall_ClassAnalyzerTest extends ParentConstructorCallee_ClassAnalyzerTest
{
    public $bar;

    public function __construct(int $b)
    {
        parent::__construct(42);
        $this->bar = $b;
    }
}
class ParentConstructorCallee_ClassAnalyzerTest
{
    public $foo;

    public function __construct(int $foo)
    {
        $this->foo = ['a' => $foo];
    }
}

it('describes arguments passed to parent constructor direct call as part of self out type on a constructor', function () {
    $constructor = $this->classAnalyzer
        ->analyze(DirectParentConstructorCall_ClassAnalyzerTest::class)
        ->getMethodDefinition('__construct');

    expect($constructor->selfOutTypeBuilder)
        ->not->toBeNull()
        ->and($constructor->getSelfOutType()->toString())
        ->toBe('self<int(42), _>');
});
class DirectParentConstructorCall_ClassAnalyzerTest extends DirectParentConstructorCallee_ClassAnalyzerTest
{
    public $bar;

    public function __construct(int $b)
    {
        parent::__construct(42);
        $this->bar = $b;
    }
}
class DirectParentConstructorCallee_ClassAnalyzerTest
{
    public $foo;

    public function __construct(int $foo)
    {
        $this->foo = $foo;
    }
}

it('describes properties set in setters as part of self out', function () {
    $constructor = $this->classAnalyzer
        ->analyze(SetterCall_ClassAnalyzerTest::class)
        ->getMethodDefinition('__construct');

    expect($constructor->selfOutTypeBuilder)
        ->not->toBeNull()
        ->and($constructor->getSelfOutType()->toString())
        ->toBe('self<TB>');
});
class SetterCall_ClassAnalyzerTest
{
    public $bar;

    public function __construct(int $b)
    {
        $this->setBar($b);
    }

    public function setBar(int $b)
    {
        $this->bar = $b;
    }
}

it('describes properties set in fluent setters as part of self out', function () {
    $constructor = $this->classAnalyzer
        ->analyze(FluentSetterCall_ClassAnalyzerTest::class)
        ->getMethodDefinition('__construct');

    expect($constructor->selfOutTypeBuilder)
        ->not->toBeNull()
        ->and($constructor->getSelfOutType()->toString())
        ->toBe('self<TB, int(42)>');
});
class FluentSetterCall_ClassAnalyzerTest
{
    public $bar;

    public $foo;

    public function __construct(int $b)
    {
        $this->setBar($b)->setFoo(42);
    }

    public function setFoo(int $f)
    {
        $this->foo = $f;

        return $this;
    }

    public function setBar(int $b)
    {
        $this->bar = $b;

        return $this;
    }
}

it('describes properties set in fluent setters set of variables as part of self out', function () {
    $constructor = $this->classAnalyzer
        ->analyze(FluentSetterOnVariablesCall_ClassAnalyzerTest::class)
        ->getMethodDefinition('__construct');

    expect($constructor->selfOutTypeBuilder)
        ->not->toBeNull()
        ->and($constructor->getSelfOutType()->toString())
        ->toBe('self<TB, int(42)>');
});
class FluentSetterOnVariablesCall_ClassAnalyzerTest
{
    public $bar;

    public $foo;

    public function __construct(int $b)
    {
        $a = $this;
        $c = $a->setBar($b);
        $c->setFoo(42);
    }

    public function setFoo(int $f)
    {
        $this->foo = $f;

        return $this;
    }

    public function setBar(int $b)
    {
        $this->bar = $b;

        return $this;
    }
}

it('playground', function () {
    //    $index = new Index;
    //    $index->registerClassDefinition(
    //        $this->classAnalyzer->analyze(Playground_ClassAnalyzerTest::class)
    //    );

    dd(
        getStatementType('new Playground_ClassAnalyzerTest(12)')->toString(),
    );
})->skip('playground');
class Playground_ClassAnalyzerTest
{
    public $foo;

    public function __construct(int $foo)
    {
        $this->foo = 12;
    }
}

it('resolves pending returns lazily', function () {
    $classDefinition = $this->classAnalyzer->analyze(Foo::class);

    $barDef = $classDefinition->getMethodDefinition('bar');
    $barReturnType = $this->resolver->resolve(
        new Scope($this->index, new NodeTypesResolver, new ScopeContext($classDefinition), new \Dedoc\Scramble\Infer\Services\FileNameResolver(new \PhpParser\NameContext(new \PhpParser\ErrorHandler\Throwing))),
        $barDef->type->getReturnType(),
    );

    expect($barReturnType->toString())->toBe('int(243)');
});

it('preserves comments in property defaults', function () {
    $classDef = $this->classAnalyzer->analyze(FooWithDefaultProperties::class);

    /** @var KeyedArrayType $defaultType */
    $defaultType = $classDef->properties['default']->defaultType;

    expect($defaultType)
        ->toBeInstanceOf(KeyedArrayType::class)
        ->and($defaultType->items[0]->getAttribute('docNode'))
        ->not->toBeNull()
        ->and($defaultType->items[1]->getAttribute('docNode'))
        ->not->toBeNull();
});

it('analyzes parent instantiation', function () {
    $this->classAnalyzer->analyze(Child::class);

    $type = getStatementType('new Dedoc\Scramble\Tests\Infer\stubs\Child("some", "wow", 42)');

    expect($type->toString())->toBe('Dedoc\Scramble\Tests\Infer\stubs\Child<int(42), string(wow), string(some)>');
});

it('analyzes deep parent instantiation', function () {
    $this->classAnalyzer->analyze(DeepChild::class);

    $type = getStatementType('new Dedoc\Scramble\Tests\Infer\stubs\DeepChild("some", "wow", 42)');

    expect($type->toString())->toBe('Dedoc\Scramble\Tests\Infer\stubs\DeepChild<int(42), string(wow), string(some)>');
});

it('analyzes parent with property promotion', function () {
    $this->classAnalyzer->analyze(ChildPromotion::class);

    $type = getStatementType('new Dedoc\Scramble\Tests\Infer\stubs\ChildPromotion("some", "wow", 42)');

    expect($type->toString())->toBe('Dedoc\Scramble\Tests\Infer\stubs\ChildPromotion<int(42), string(wow), string(some)>');
});

it('analyzes call to parent setter methods in child constructor', function () {
    $this->classAnalyzer->analyze(ChildParentSetterCalls::class);

    $type = getStatementType('new Dedoc\Scramble\Tests\Infer\stubs\ChildParentSetterCalls("some", "wow")');

    expect($type->toString())->toBe('Dedoc\Scramble\Tests\Infer\stubs\ChildParentSetterCalls<string(from ChildParentSetterCalls constructor), string(from ChildParentSetterCalls wow), string(some)>');
});

it('analyzes fluent setters called in constructor', function () {
    $this->classAnalyzer->analyze(Foo_ClassAnalyzerTest::class);

    $type = getStatementType('new Foo_ClassAnalyzerTest()');

    expect($type->toString())->toBe('Foo_ClassAnalyzerTest<int(42), string(baz)>');
});
class Foo_ClassAnalyzerTest
{
    public int $foo;

    public string $bar;

    public function __construct()
    {
        $this
            ->setFoo(42)
            ->setBar('baz');
    }

    public function setFoo($number)
    {
        $this->foo = $number;

        return $this;
    }

    public function setBar($string)
    {
        $this->bar = $string;

        return $this;
    }
}

it('analyzes not fluent setters called in constructor', function () {
    $this->classAnalyzer->analyze(FooNotFluent_ClassAnalyzerTest::class);

    $type = getStatementType('new FooNotFluent_ClassAnalyzerTest()');

    expect($type->toString())->toBe('FooNotFluent_ClassAnalyzerTest<int(42), string(baz)>');
});
class FooNotFluent_ClassAnalyzerTest
{
    public int $foo;

    public string $bar;

    public function __construct()
    {
        $this->setFoo(42);
        $this->setBar('baz');
    }

    public function setFoo($number)
    {
        $this->foo = $number;
    }

    public function setBar($string)
    {
        $this->bar = $string;
    }
}

it('analyzes static method call on class constants', function () {
    $this->classAnalyzer->analyze(ConstFetchStaticCallChild_ClassAnalyzerTest::class);

    $type = getStatementType('(new ConstFetchStaticCallChild_ClassAnalyzerTest)->staticMethodCall()');

    expect($type->toString())->toBe('int(42)');
});

it('analyzes new call on class constants', function () {
    $this->classAnalyzer->analyze(ConstFetchStaticCallChild_ClassAnalyzerTest::class);

    $type = getStatementType('(new ConstFetchStaticCallChild_ClassAnalyzerTest)->newCall()');

    expect($type->toString())->toBe('ConstFetchStaticCallFoo_ClassAnalyzerTest');
});

class ConstFetchStaticCallParent_ClassAnalyzerTest
{
    public function staticMethodCall()
    {
        return (static::FOO_CLASS)::foo();
    }

    public function newCall()
    {
        return new (static::FOO_CLASS);
    }
}
class ConstFetchStaticCallChild_ClassAnalyzerTest extends ConstFetchStaticCallParent_ClassAnalyzerTest
{
    public const FOO_CLASS = ConstFetchStaticCallFoo_ClassAnalyzerTest::class;
}
class ConstFetchStaticCallFoo_ClassAnalyzerTest
{
    public static function foo()
    {
        return 42;
    }
}

it('analyzes call on typed properties', function () {
    $this->classAnalyzer->analyze(InjectedProperty_ClassAnalyzerTest::class);

    $type = getStatementType('(new InjectedProperty_ClassAnalyzerTest)->bar()');

    expect($type->toString())->toBe('int(42)');
});

class InjectedProperty_ClassAnalyzerTest
{
    public function __construct(private FooProperty_ClassAnalyzerTest $fooProp) {}

    public function bar()
    {
        return $this->fooProp->foo();
    }
}
class FooProperty_ClassAnalyzerTest
{
    public function foo()
    {
        return 42;
    }
}
