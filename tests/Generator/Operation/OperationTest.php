<?php

it('deprecated key is properly set', function () {
    $operation = new \Dedoc\Scramble\Support\Generator\Operation('get');
    $operation->deprecated(true);

    $array = $operation->toArray();

    expect($operation->deprecated)->toBeTrue()
        ->and($array)->toHaveKey('deprecated')
        ->and($array['deprecated'])->toBeTrue();
});

it('default deprecated key is false', function () {
    $operation = new \Dedoc\Scramble\Support\Generator\Operation('get');

    $array = $operation->toArray();

    expect($operation->deprecated)->toBeFalse()
        ->and($array)->not()->toHaveKey('deprecated');
});

it('set extension property', function () {
    $operation = new \Dedoc\Scramble\Support\Generator\Operation('get');
    $operation->setExtensionProperty('custom-key', 'custom-value');

    $array = $operation->toArray();

    expect($array)->toBe(['x-custom-key' => 'custom-value']);
});

it('serializes parameter references in toArray', function () {
    $components = new \Dedoc\Scramble\Support\Generator\Components;
    $reference = new \Dedoc\Scramble\Support\Generator\Reference('parameters', 'MyParameter', $components);

    $operation = new \Dedoc\Scramble\Support\Generator\Operation('get');
    $operation->addParameters([
        \Dedoc\Scramble\Support\Generator\Parameter::make('foo', 'query'),
        $reference,
    ]);

    $array = $operation->toArray();

    expect($array['parameters'])->toBe([
        [
            'name' => 'foo',
            'in' => 'query',
        ],
        ['$ref' => '#/components/parameters/MyParameter'],
    ]);
});
