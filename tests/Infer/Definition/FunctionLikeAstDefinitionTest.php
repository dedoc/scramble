<?php

namespace Dedoc\Scramble\Tests\Infer\Definition;

use Dedoc\Scramble\Infer\Reflector\MethodReflector;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Tests\TestUtils;

test('prefers return declaration type if inferred is not compatible', function () {
    $def = TestUtils::buildAstFunctionDefinition(
        MethodReflector::make(Foo_FunctionLikeAstDefinitionTest::class, 'foo'),
    );

    expect($def->getReturnType()->toString())->toBe('int');
});

test('prefers return phpdoc type if inferred is not compatible', function () {
    $def = TestUtils::buildAstFunctionDefinition(
        MethodReflector::make(Foo_FunctionLikeAstDefinitionTest::class, 'bar'),
    );

    expect($def->getReturnType()->toString())->toBe('int');
});

test('prefers scramble-return type even if inferred is concrete', function () {
    $def = TestUtils::buildAstFunctionDefinition(
        MethodReflector::make(Foo_FunctionLikeAstDefinitionTest::class, 'baz'),
    );

    expect($def->getReturnType()->toString())->toBe('int');
});

test('checks object acceptance using represented class hierarchy', function () {
    $builder = new ObjectType(Builder_FunctionLikeAstDefinitionTest::class);
    $customBuilder = new ObjectType(CustomBuilder_FunctionLikeAstDefinitionTest::class);

    expect($customBuilder->acceptedBy($builder))->toBeTrue()
        ->and($builder->acceptedBy($customBuilder))->toBeFalse();
});

class Builder_FunctionLikeAstDefinitionTest {}

class CustomBuilder_FunctionLikeAstDefinitionTest extends Builder_FunctionLikeAstDefinitionTest {}

class Foo_FunctionLikeAstDefinitionTest
{
    public function foo(): int
    {
        return unk();
    }

    /**
     * @return int
     */
    public function bar()
    {
        return unk();
    }

    /**
     * @scramble-return int
     */
    public function baz()
    {
        return 42;
    }
}
