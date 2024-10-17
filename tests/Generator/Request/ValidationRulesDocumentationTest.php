<?php

use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\DeepParametersMerger;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;

function validationRulesToDocumentationWithDeep_Clone (array $rules) {
    return (new DeepParametersMerger(collect(app()->make(RulesToParameters::class, ['rules' => $rules])->handle())))
        ->handle();
}

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

it('supports confirmed rule in array', function () {
    $rules = [
        'user.password' => ['required', 'min:8', 'confirmed'],
    ];

    $params = validationRulesToDocumentationWithDeep_Clone($rules);

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(1)
        ->and($params[0])
        ->toMatchArray([
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'password' => ['type' => 'string', 'minLength' => 8],
                    'password_confirmation' => ['type' => 'string', 'minLength' => 8],
                ],
                'required' => ['password', 'password_confirmation'],
            ],
        ]);
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

it('works when last validation item is items array', function () {
    $rules = [
        'items.*.name' => 'required|string',
        'items.*.email' => 'email',
        'items.*' => 'array',
        'items' => ['array', 'min:1', 'max:10'],
    ];

    $params = validationRulesToDocumentationWithDeep_Clone($rules);

    expect($params = collect($params)->map->toArray()->all())
        ->toBe([
            [
                'name' => 'items',
                'in' => 'query',
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                            ],
                            'email' => [
                                'type' => 'string',
                                'format' => 'email',
                            ],
                        ],
                        'required' => [
                            0 => 'name',
                        ],
                    ],
                    'minItems' => 1.0,
                    'maxItems' => 10.0,
                ],
            ],
        ]);
});
