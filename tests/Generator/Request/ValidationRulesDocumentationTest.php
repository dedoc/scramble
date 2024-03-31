<?php

use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;

it('supports confirmed rule', function () {
    $rules = [
        'password' => ['required', 'min:8', 'confirmed'],
    ];

    $params = app()->make(RulesToParameters::class, ['rules' => $rules])->handle();

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(2)
        ->and($params[1])
        ->toMatchArray(['name' => 'password_confirmation']);
});

it('supports multiple confirmed rule', function () {
    $rules = [
        'password' => ['required', 'min:8', 'confirmed'],
        'email' => ['required', 'email', 'confirmed'],
    ];

    $params = app()->make(RulesToParameters::class, ['rules' => $rules])->handle();

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(4)
        ->and($params[2])
        ->toMatchArray(['name' => 'password_confirmation'])
        ->and($params[3])
        ->toMatchArray(['name' => 'email_confirmation']);
});
