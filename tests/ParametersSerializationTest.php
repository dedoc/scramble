<?php

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;

it('checks return type of param when "style" and "explode" specified', function () {
    $type = new StringType;
    $type->enum(['products', 'categories', 'condition']);

    $parameter = new Parameter('includes', 'query');
    $parameter->setSchema(Schema::fromType($type));
    $parameter->setExplode(false);
    $parameter->setStyle('form');

    expect($parameter->toArray())->toBe([
        'name' => 'includes',
        'in' => 'query',
        'style' => 'form',
        'explode' => false,
        'schema' => [
            'type' => 'string',
            'enum' => [
                'products',
                'categories',
                'condition',
            ],
        ],
    ]);
});

it('checks return type of param when "style" and "explode" not specified', function () {
    $type = new StringType;
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
                'condition',
            ],
        ],
    ]);
});

it('checks return type of param when "allowReserved" specified', function () {
    $type = new StringType;

    $parameter = new Parameter('filter', 'query');
    $parameter->setSchema(Schema::fromType($type));
    $parameter->setAllowReserved(true);

    expect($parameter->toArray())->toBe([
        'name' => 'filter',
        'in' => 'query',
        'allowReserved' => true,
        'schema' => [
            'type' => 'string',
        ],
    ]);
});
