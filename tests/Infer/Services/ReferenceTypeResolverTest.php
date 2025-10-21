<?php

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeAstDefinitionBuilder;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\AbstractType;
use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;

beforeEach(function () {
    $this->index = app(Index::class);

    $this->classAnalyzer = new ClassAnalyzer($this->index);

    $this->resolver = new ReferenceTypeResolver($this->index);
});

/*
 * Late static binding
 */

/*
 * New calls
 */
it('infers new calls on parent class', function (string $method, string $expectedType) {
    $methodDef = $this->classAnalyzer
        ->analyze(\Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Foo::class)
        ->getMethodDefinition($method);
    expect($methodDef->type->getReturnType()->toString())->toBe($expectedType);
})->with([
    ['newSelfCall', 'Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Foo'],
    ['newStaticCall', 'Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Foo'],
]);

it('infers new calls on child class', function (string $method, string $expectedType) {
    $methodDef = $this->classAnalyzer
        ->analyze(\Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Bar::class)
        ->getMethodDefinition($method);
    expect($methodDef->type->getReturnType()->toString())->toBe($expectedType);
})->with([
    ['newSelfCall', 'Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Foo'],
    ['newStaticCall', 'Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Bar<string(foo)>'],
    ['newParentCall', 'Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Foo'],
]);

/*
 * Static method calls (should work the same for both static and non-static methods)
 */
it('infers static method calls on parent class', function (string $method, string $expectedType) {
    $methodDef = $this->classAnalyzer
        ->analyze(\Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Foo::class)
        ->getMethodDefinition($method);
    expect($methodDef->type->getReturnType()->toString())->toBe($expectedType);
})->with([
    ['selfMethodCall', 'string(foo)'],
    ['staticMethodCall', 'string(foo)'],
]);

it('infers static method calls on child class', function (string $method, string $expectedType) {
    $methodDef = $this->classAnalyzer
        ->analyze(\Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Bar::class)
        ->getMethodDefinition($method);
    expect($methodDef->type->getReturnType()->toString())->toBe($expectedType);
})->with([
    ['selfMethodCall', 'string(foo)'],
    ['staticMethodCall', 'string(bar)'],
    ['parentMethodCall', 'string(foo)'],
]);

it('infers static class fetch on parent', function (string $method, string $expectedType) {
    $methodDef = $this->classAnalyzer
        ->analyze(\Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Foo::class)
        ->getMethodDefinition($method);
    expect($methodDef->type->getReturnType()->toString())->toBe($expectedType);
})->with([
    ['staticClassFetch', 'class-string<Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Foo>'],
]);

it('infers static class fetch on child', function (string $method, string $expectedType) {
    $methodDef = $this->classAnalyzer
        ->analyze(\Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Bar::class)
        ->getMethodDefinition($method);
    expect($methodDef->type->getReturnType()->toString())->toBe($expectedType);
})->with([
    ['staticClassFetch', 'class-string<Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Bar>'],
]);

it('infers static class fetch on child when called from outside', function (string $method, string $expectedType) {
    $methodDef = $this->classAnalyzer
        ->analyze(CallRef_ReferenceTypeResolverTest::class)
        ->getMethodDefinition($method);
    expect($methodDef->type->getReturnType()->toString())->toBe($expectedType);
})->with([
    ['baz', 'class-string<Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Bar>'],
]);
class CallRef_ReferenceTypeResolverTest
{
    public static function baz()
    {
        return static::foo();
    }

    public static function foo()
    {
        return \Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Bar::staticClassFetch();
    }
}

it('complex static call and property fetch', function () {
    $type = getStatementType('Dedoc\Scramble\Tests\Infer\Services\StaticCallsClasses\Bar::wow()');

    expect($type->toString())->toBe('string(foo)');
});

/*
 * Static method calls
 */
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

/*
 * Ability to override accepted by type and track annotated types
 */
it('allows overriding types accepted by another type', function () {
    $functionType = new FunctionType(
        'wow',
        returnType: $expectedReturnType = new class('sample') extends ObjectType
        {
            public function acceptedBy(Type $otherType): bool
            {
                return $otherType instanceof StringType;
            }
        },
    );
    $functionType->setAttribute(
        'annotatedReturnType',
        new StringType,
    );

    $def = new FunctionLikeDefinition($functionType);

    FunctionLikeAstDefinitionBuilder::resolveFunctionReturnReferences(
        new GlobalScope,
        $def,
    );

    expect($actualReturnType = $functionType->getReturnType())
        ->toBeInstanceOf(ObjectType::class)
        ->and($actualReturnType->name)
        ->toBe($expectedReturnType->name);
});

it('resolves only arguments with templates referenced in return type', function () {
    $templates = [$t = new TemplateType('T')];
    $fn = tap(new FunctionType(
        '_',
        arguments: ['foo' => $t],
        returnType: new LiteralStringType('wow'),
    ), fn ($f) => $f->templates = $templates);

    expect(ReferenceTypeResolver::getInstance()->resolve(
        new GlobalScope,
        new CallableCallReferenceType($fn, [
            new class extends AbstractType implements LateResolvingType
            {
                public function resolve(): Type
                {
                    throw new LogicException('should not happen');
                }

                public function isResolvable(): bool
                {
                    return true;
                }

                public function isSame(Type $type)
                {
                    return false;
                }

                public function toString(): string
                {
                    return '__test__';
                }
            },
        ]),
    )->toString())->toBe('string(wow)');
});
