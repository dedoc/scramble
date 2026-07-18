<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

describe('length aware paginator annotations (introduced in 11.15.0)', function () {
    it('handles through', function () {
        $paginatorType = new Generic(LengthAwarePaginator::class, [
            new IntegerType,
            new ObjectType(Model::class),
        ]);

        $typeAfterThrough = ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new MethodCallReferenceType($paginatorType, 'through', [
                    new FunctionType('{}', [], new ObjectType(SamplePostModel::class)),
                ]),
            );

        expect($typeAfterThrough->toString())->toBe(LengthAwarePaginator::class.'<int, '.SamplePostModel::class.'>');
    });
})->skip(fn () => ! version_compare(app()->version(), '11.15.0', '>='));
