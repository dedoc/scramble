<?php

use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Database\Eloquent\Builder;

it('treats same-arity functions with differently named parameters as the same when arg types match', function () {
    $withQuery = new FunctionType('anonymous', [
        'query' => new ObjectType(Builder::class),
        'filter' => new ObjectType(Builder::class),
    ], new UnknownType);

    $withQ = new FunctionType('anonymous', [
        'q' => new ObjectType(Builder::class),
        'filter' => new ObjectType(Builder::class),
    ], new UnknownType);

    expect($withQuery->isSame($withQ))->toBeTrue()
        ->and($withQ->isSame($withQuery))->toBeTrue();
});

it('does not treat functions with different argument counts as the same', function () {
    $twoArgs = new FunctionType('anonymous', [
        'query' => new TemplateType('TQuery'),
        'filter' => new TemplateType('TFilter'),
    ], new UnknownType);

    $threeArgs = new FunctionType('anonymous', [
        'query' => new TemplateType('TQuery'),
        'filter' => new TemplateType('TFilter'),
        'request' => new TemplateType('TRequest'),
    ], new UnknownType);

    expect($twoArgs->isSame($threeArgs))->toBeFalse()
        ->and($threeArgs->isSame($twoArgs))->toBeFalse();
});

it('merges generics containing differently named function params without error', function () {
    $a = new Generic(Builder::class, [
        new FunctionType('anonymous', [
            'query' => new TemplateType('TQuery'),
            'filter' => new TemplateType('TFilter'),
        ], new UnknownType),
    ]);
    $b = new Generic(Builder::class, [
        new FunctionType('anonymous', [
            'q' => new TemplateType('TQ'),
            'filter' => new TemplateType('TFilter'),
        ], new UnknownType),
    ]);

    expect(fn () => TypeHelper::mergeTypes($a, $b))->not->toThrow(Throwable::class);
});
