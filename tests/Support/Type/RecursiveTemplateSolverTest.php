<?php

namespace Dedoc\Scramble\Tests\Support\Type;

use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\RecursiveTemplateSolver;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->solver = new RecursiveTemplateSolver;
});

it('finds simplest type', function () {
    $foundType = $this->solver->solve(
        $t = new TemplateType('T'),
        new IntegerType,
        $t,
    );

    expect($foundType->toString())->toBe('int');
});

it('finds union type', function () {
    $foundType = $this->solver->solve(
        new Union([
            $t = new TemplateType('T'),
            new TemplateType('G'),
        ]),
        new Union([
            new IntegerType,
            new StringType,
        ]),
        $t,
    );

    expect($foundType->toString())->toBe('int|string');
});

it('finds generic type', function () {
    $foundType = $this->solver->solve(
        new Union([
            new Generic('A', [new IntegerType, $t = new TemplateType('T')]),
            new Generic(Collection::class, [new IntegerType, $t]),
        ]),
        new Generic(Collection::class, [new IntegerType, new IntegerType]),
        $t,
    );

    expect($foundType->toString())->toBe('int');
});

it('finds structural matching type with generics', function () {
    $foundType = $this->solver->solve(
        new Union([
            $t = new TemplateType('T'),
            new Generic(Collection::class, [new IntegerType, $t]),
        ]),
        new Generic(Collection::class, [new IntegerType, new IntegerType]),
        $t,
    );

    expect($foundType->toString())->toBe('int');
});

it('finds structural matching type with callables', function () {
    $foundType = $this->solver->solve(
        new Union([
            $t = new TemplateType('T'),
            new FunctionType('{}', [], $t),
        ]),
        new FunctionType('{}', [], new IntegerType),
        $t,
    );

    expect($foundType->toString())->toBe('int');
});

it('finds structural matching type with arrays and iterables', function () {
    $foundType = $this->solver->solve(
        new Generic('iterable', [new IntegerType, $t = new TemplateType('T')]),
        new ArrayType(new LiteralIntegerType(42)),
        $t,
    );

    expect($foundType->toString())->toBe('int(42)');
});
