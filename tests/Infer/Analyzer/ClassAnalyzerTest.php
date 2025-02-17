<?php

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Tests\Infer\stubs\Bar;
use Dedoc\Scramble\Tests\Infer\stubs\Child;
use Dedoc\Scramble\Tests\Infer\stubs\ChildParentSetterCalls;
use Dedoc\Scramble\Tests\Infer\stubs\ChildPromotion;
use Dedoc\Scramble\Tests\Infer\stubs\DeepChild;
use Dedoc\Scramble\Tests\Infer\stubs\Foo;
use Dedoc\Scramble\Tests\Infer\stubs\FooWithTrait;

beforeEach(function () {
    $this->index = app(Index::class);

    $this->classAnalyzer = new ClassAnalyzer($this->index);

    $this->resolver = new ReferenceTypeResolver($this->index);
});

it('creates a definition from the given class', function () {
    $definition = $this->classAnalyzer->analyze(Foo::class);

    expect($this->index->classesDefinitions)
        ->toHaveKeys([Foo::class, Bar::class])
        ->and($definition->methods)->toHaveKey('foo')
        ->and(($fooRawDef = $definition->methods['foo'])->isFullyAnalyzed())->toBeFalse()
        ->and($fooRawDef->type->getReturnType()->toString())->toBe('unknown');
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

    expect($fqnDef->type->getReturnType()->toString())->toBe('string('.Foo::class.')');
});

it('resolves pending returns lazily', function () {
    $classDefinition = $this->classAnalyzer->analyze(Foo::class);

    $barDef = $classDefinition->getMethodDefinition('bar');
    $barReturnType = $this->resolver->resolve(
        new Scope($this->index, new NodeTypesResolver, new ScopeContext($classDefinition), new \Dedoc\Scramble\Infer\Services\FileNameResolver(new \PhpParser\NameContext(new \PhpParser\ErrorHandler\Throwing))),
        $barDef->type->getReturnType(),
    );

    expect($barReturnType->toString())->toBe('int(243)');
});

it('analyzes traits', function () {
    $classDef = $this->classAnalyzer->analyze(FooWithTrait::class);

    expect($classDef->properties)->toHaveCount(1)->toHaveKeys([
        'propBaz',
    ]);
    expect($classDef->methods)->toHaveCount(3)->toHaveKeys([
        'something',
        'methodBaz',
        'methodInvokingFooTraitMethod',
    ]);
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
class Foo_ClassAnalyzerTest {
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
class FooNotFluent_ClassAnalyzerTest {
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
