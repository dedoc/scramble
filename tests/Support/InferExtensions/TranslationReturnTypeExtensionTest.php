<?php

use Dedoc\Scramble\Support\InferExtensions\TranslationReturnTypeExtension;

it('infers __ type from string literal by running translation', function () {
    app('translator')->addLines(['messages.success' => 'Success!'], 'en');

    $type = getStatementType("__('messages.success')", [
        new TranslationReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('string(Success!)');
});

it('returns key as literal when translation is missing', function () {
    $type = getStatementType("__('missing.key')", [
        new TranslationReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('string(missing.key)');
});

it('infers __ type as string when replace array is passed', function () {
    $type = getStatementType("__('Hello :name', ['name' => 'John'])", [
        new TranslationReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('string');
});

it('passes through non-literal single argument type', function () {
    $type = getStatementType("__(['foo' => 'bar'])", [
        new TranslationReturnTypeExtension,
    ]);

    expect($type->toString())->toBe('array{foo: string(bar)}');
});
