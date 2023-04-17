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
