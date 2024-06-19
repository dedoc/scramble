<?php

use \Dedoc\Scramble\Support\Generator\Parameter;
use \Dedoc\Scramble\Support\Generator\Types\StringType;
use \Dedoc\Scramble\Support\Generator\Schema;

it('checks return type of $parameter->toArray() when "style" and "explode" specified', function () {
    $type = new StringType();
    $type->enum(['products', 'categories', 'condition']);

    $parameter = new Parameter('includes', 'query');
    $parameter->setSchema(Schema::fromType($type));
    $parameter->setExplode(false);
    $parameter->setStyle('form');

    expect($parameter->toArray())->toBe([
        'name' => 'includes',
        'in' => 'query',
        'schema' => [
            'type' => 'string',
            'enum' => [
                'products',
                'categories',
                'condition'
            ]
        ],
        'style' => 'form',
        'explode' => false
    ]);
});

it('checks return type of $parameter->toArray() when "style" and "explode" not specified', function () {
    $type = new StringType();
    $type->enum(['products', 'categories', 'condition']);

    $parameter = new Parameter('includes', 'query');
    $parameter->setSchema(Schema::fromType($type));

    expect($parameter->toArray())->toBe([
        'name' => 'includes',
        'in' => 'query',
        'schema' => [
            'type' => 'string',
            'enum' => [
                'products',
                'categories',
                'condition'
            ]
        ]
    ]);
});
