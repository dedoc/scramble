<?php

use Dedoc\Scramble\Support\InferExtensions\FacadeStaticMethodReturnTypeExtension;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

it('infers DB::transaction return type from closure', function () {
    $type = getStatementType(DB::class.'::transaction(fn () => 1)', [
        new FacadeStaticMethodReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('int(1)');
});

it('infers Config::string return type', function () {
    $type = getStatementType(Config::class.'::string(\'app.name\')', [
        new FacadeStaticMethodReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('string');
});

it('infers Cache::has return type', function () {
    $type = getStatementType(Cache::class.'::has(\'foo\')', [
        new FacadeStaticMethodReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('boolean');
});
