<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Support\Collection;

it('supports this in map', function () {
    $collectionType = new Generic(Collection::class, [
        new IntegerType,
        new ObjectType(SamplePostModel::class),
    ]);

    $typeAfterMap = ReferenceTypeResolver::getInstance()
        ->resolve(
            tap(new GlobalScope, function (GlobalScope $s) {
                $s->context = new ScopeContext(
                    classDefinition: app(Infer::class)->analyzeClass(SamplePostModel::class),
                    functionDefinition: null,
                );
            }),
            (new MethodCallReferenceType($collectionType, 'map', [
                new FunctionType('{}', [], new SelfType(SamplePostModel::class)),
            ])),
        );

    expect($typeAfterMap->toString())->toBe(Collection::class.'<int, '.SamplePostModel::class.'>');
});
