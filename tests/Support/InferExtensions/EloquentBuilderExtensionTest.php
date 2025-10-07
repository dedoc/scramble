<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Database\Eloquent\Builder;

test('forwards call to scope', function (string $method, string $expectedType) {
    $type = ReferenceTypeResolver::getInstance()
        ->resolve(
            new GlobalScope,
            new MethodCallReferenceType(
                new Generic(Builder::class, [new ObjectType(SamplePostModel::class)]),
                $method,
                [],
            )
        );

    expect($type->toString())->toBe($expectedType);
})->with([
    ['approved', Builder::class.'<'.SamplePostModel::class.'>'],
    ['approvedTypedParam', Builder::class.'<'.SamplePostModel::class.'>'],
]);
