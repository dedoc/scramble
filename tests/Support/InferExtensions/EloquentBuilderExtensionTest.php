<?php

namespace Dedoc\Scramble\Tests\Support\InferExtensions;

use Dedoc\Scramble\Tests\Files\SamplePostModel;
use Illuminate\Database\Eloquent\Builder;

test('forwards call to scope', function (string $method, string $expectedType) {
    dump(getStatementType(SamplePostModel::class.'::query()')->toString());

    $type = getStatementType(SamplePostModel::class.'::query()->'.$method.'()');

    expect($type->toString())->toBe($expectedType);
})->with([
    ['approved', Builder::class.'<'.SamplePostModel::class.'>'],
    ['approvedTypedParam', Builder::class.'<'.SamplePostModel::class.'>'],
]);
