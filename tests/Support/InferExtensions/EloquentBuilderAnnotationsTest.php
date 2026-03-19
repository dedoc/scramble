<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Typed `when` was added in 9.x, so no need to skip test.
 */
it('supports when', function () {
    $builderType = new Generic(Builder::class, [
        new ObjectType(SamplePostModel::class),
    ]);

    $typeAfterWhen = ReferenceTypeResolver::getInstance()
        ->resolve(
            new GlobalScope,
            (new MethodCallReferenceType($builderType, 'when', [
                new FunctionType('{}', [], new LiteralBooleanType(true)),
                (function () {
                    $t = new TemplateType('TQ');
                    $fnType = new FunctionType('{}', [
                        'a' => $t,
                    ], $t);
                    $fnType->templates = [$t];

                    return $fnType;
                })(),
            ])),
        );

    expect($typeAfterWhen->toString())->toBe(Builder::class.'<'.SamplePostModel::class.'>');
});
