<?php

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
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
        returnType: $expectedReturnType = new class ('sample') extends ObjectType {
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

    (new ReferenceTypeResolver(new Index))->resolveFunctionReturnReferences(
        new GlobalScope(),
        $functionType,
    );

    expect($actualReturnType = $functionType->getReturnType())
        ->toBeInstanceOf(ObjectType::class)
        ->and($actualReturnType->name)
        ->toBe($expectedReturnType->name);
});
