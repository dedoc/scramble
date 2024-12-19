<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\IntegerType as GeneratorIntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType as GeneratorStringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType as GeneratorUnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EnumToSchema;

beforeEach(function () {
    $this->infer = new Infer(new Infer\Scope\Index);

    $transformer = new TypeTransformer($this->infer, new Components);
    $this->extension = new EnumToSchema(
        $this->infer,
        $transformer,
        $transformer->getComponents(),
    );
});

it('should handle when is instance of ObjectType and exists', function () {
    $type = new ObjectType(FooEnum::class);

    expect($this->extension->shouldHandle($type))
        ->toBeTrue();
});

it('should not handle when is instance of ObjectType and not exists', function () {
    $type = new ObjectType(__CLASS__);

    expect($this->extension->shouldHandle($type))
        ->toBeFalse();
});

it('should not handle when is not instance of ObjectType', function () {
    $type = new UnknownType;

    expect($this->extension->shouldHandle($type))
        ->toBeFalse();
});

it('should return UnknownType when enum does not have values', function () {
    $type = new ObjectType(FooEnum::class);

    expect($this->extension->toSchema($type))
        ->toBeInstanceOf(GeneratorUnknownType::class)
        ->enum
        ->toBeEmpty();
});

it('should return StringType when is a string backed enum', function () {
    $type = new ObjectType(StringEnum::class);

    expect($this->extension->toSchema($type))
        ->toBeInstanceOf(GeneratorStringType::class)
        ->enum
        ->toBe([
            'foo',
            'bar',
        ]);
});

it('should return IntegerType when is a integer backed enum', function () {
    $type = new ObjectType(IntegerEnum::class);

    expect($this->extension->toSchema($type))
        ->toBeInstanceOf(GeneratorIntegerType::class)
        ->enum
        ->toBe([
            1,
            2,
        ]);
});

it('should return enum name as schema name when not annotated', function () {
    $type = new ObjectType(NotAnnotatedEnum::class);

    expect($this->extension->reference($type))
        ->toBeInstanceOf(Reference::class)
        ->getUniqueName()
        ->toBe('NotAnnotatedEnum');
});

it('should return schema name equals to annotation when annotated', function () {
    $type = new ObjectType(AnnotatedEnum::class);

    expect($this->extension->reference($type))
        ->toBeInstanceOf(Reference::class)
        ->getUniqueName()
        ->toBe('AnnotatedSchemaName');
});

enum FooEnum
{
    case Foo;
    case Bar;
}

enum StringEnum: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}

enum IntegerEnum: int
{
    case Foo = 1;
    case Bar = 2;
}

enum NotAnnotatedEnum {}

/**
 * @schemaName AnnotatedSchemaName
 */
enum AnnotatedEnum {}
