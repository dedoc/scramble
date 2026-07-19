<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Dedoc\Scramble\Support\Type\TemplateType;

it('builds reflection based definition upon request', function () {
    $index = new LazyShallowReflectionIndex;

    $definition = $index->getClass(Foo_LazyShallowReflectionIndexTest::class);

    expect($definition->getData()->name)
        ->toBe(Foo_LazyShallowReflectionIndexTest::class)
        ->and(count($definition->getData()->methods))
        ->toBe(0);
});
it('builds method definition upon request', function () {
    $index = new LazyShallowReflectionIndex;

    $methodDefinition = $index->getClass(Foo_LazyShallowReflectionIndexTest::class)->getMethod('foo');

    expect($methodDefinition->type->getReturnType()->toString())
        ->toBe('int');
});

it('builds property types from PHPDoc', function () {
    $lazyDefinition = (new LazyShallowReflectionIndex)
        ->getClass(PhpDocProperties_LazyShallowReflectionIndexTest::class);

    if (! $lazyDefinition) {
        throw new \RuntimeException('Expected the PHPDoc properties fixture to be reflected.');
    }

    $definition = $lazyDefinition->getData();

    $propertyType = $definition->getPropertyDefinition('number')->type;
    $untypedPropertyType = $definition->getPropertyDefinition('untypedNumber')->type;
    $conflictingPropertyType = $definition->getPropertyDefinition('nativeNumber')->type;
    $magicPropertyType = $definition->getPropertyDefinition('magic')->type;

    expect($propertyType)->toBeInstanceOf(TemplateType::class)
        ->and($propertyType->is?->toString())->toBe('int<1, max>')
        ->and($untypedPropertyType->is?->toString())->toBe('int<1, max>')
        ->and($conflictingPropertyType->is?->toString())->toBe('int')
        ->and($magicPropertyType->toString())->toBe('string')
        ->and($magicPropertyType->getAttribute('source'))->toBe('phpDoc');
});

class Foo_LazyShallowReflectionIndexTest
{
    public int $foo = 42;

    public function foo(): int {}
}

/**
 * @property string $magic
 * @property string $nativeNumber
 */
class PhpDocProperties_LazyShallowReflectionIndexTest
{
    /** @var positive-int */
    public int $number;

    /** @var positive-int */
    public $untypedNumber;

    public int $nativeNumber;
}
