<?php

use Dedoc\Scramble\Support\InferExtensions\FacadeStaticMethodReturnTypeExtension;
use Illuminate\Support\Facades\DB;

it('infers DB::transaction return type from closure', function () {
    $type = getStatementType(DB::class.'::transaction(fn () => 1)', [
        new FacadeStaticMethodReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('int(1)');
});
