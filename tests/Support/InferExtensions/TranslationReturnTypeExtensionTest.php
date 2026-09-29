<?php

use Dedoc\Scramble\Support\InferExtensions\TranslationReturnTypeExtension;

it('infers __ type from string literal by running translation', function () {
    app('translator')->addLines(['messages.success' => 'Success!'], 'en');

    $type = getStatementType("__('messages.success')", [
        app(TranslationReturnTypeExtension::class),
    ]);

    expect($type->toString())->toBe('string(Success!)');
});

it('returns key as literal when translation is missing', function () {
    $type = getStatementType("__('missing.key')", [
        app(TranslationReturnTypeExtension::class),
    ]);

    expect($type->toString())->toBe('string(missing.key)');
});

it('resolves __ type as keyed array when translation is an array', function () {
    app('translator')->addLines(['messages.labels' => ['name' => 'Name', 'email' => 'E-mail']], 'en');

    $type = getStatementType("__('messages.labels')", [
        app(TranslationReturnTypeExtension::class),
    ]);

    expect($type->toString())->toBe('array{name: string(Name), email: string(E-mail)}');
});

it('resolves __ type as list when translation is a list', function () {
    app('translator')->addLines(['messages.steps' => ['First', 'Second']], 'en');

    $type = getStatementType("__('messages.steps')", [
        app(TranslationReturnTypeExtension::class),
    ]);

    expect($type->toString())->toBe('list{string(First), string(Second)}');
});

it('infers __ type as string when replace array is passed', function () {
    $type = getStatementType("__('Hello :name', ['name' => 'John'])", [
        app(TranslationReturnTypeExtension::class),
    ]);

    expect($type->toString())->toBe('string');
});

it('passes through non-literal single argument type', function () {
    $type = getStatementType("__(['foo' => 'bar'])", [
        app(TranslationReturnTypeExtension::class),
    ]);

    expect($type->toString())->toBe('array{foo: string(bar)}');
});
