<?php

use Dedoc\Scramble\Support\InferExtensions\RequestFunctionReturnTypeExtension;
use Illuminate\Http\Request;

it('infers request without arguments as a request that may contain request input', function () {
    $type = getStatementType('request()', [
        new RequestFunctionReturnTypeExtension,
    ]);

    expect($type->toString())->toBe(Request::class)
        ->and($type->getAttribute('mayContainRequestInput'))->toBeTrue();
});
