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

it('works when last validation item is items array', function () {
    $rules = [
        'items.*.name' => 'required|string',
        'items.*.email' => 'email',
        'items.*' => 'array',
        'items' => ['array', 'min:1', 'max:10'],
    ];

    $params = app()->make(RulesToParameters::class, ['rules' => $rules])->handle();

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
