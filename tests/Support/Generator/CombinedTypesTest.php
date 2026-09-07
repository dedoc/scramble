<?php

namespace Dedoc\Scramble\Tests\Support\Generator;

use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Combined\OneOf;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Discriminator;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

it('serializes combined types', function (string $combinedTypeClass, string $key) {
    $type = (new $combinedTypeClass)->setItems([new StringType, new IntegerType]);

    expect($type->toArray())->toBe([
        $key => [
            ['type' => 'string'],
            ['type' => 'integer'],
        ],
    ]);
})->with([
    [OneOf::class, 'oneOf'],
    [AnyOf::class, 'anyOf'],
    [AllOf::class, 'allOf'],
]);

it('serializes a discriminator', function () {
    $components = new Components;
    $components->addSchema('App\Dto\Cat', Schema::fromType(new ObjectType));

    $type = (new OneOf)
        ->setItems([$reference = $components->getSchemaReference('App\Dto\Cat')])
        ->setDiscriminator(new Discriminator('petType', [
            'cat' => $reference,
            'dog' => 'Dog',
        ]));

    expect($type->toArray())->toBe([
        'oneOf' => [
            ['$ref' => '#/components/schemas/Cat'],
        ],
        'discriminator' => [
            'propertyName' => 'petType',
            'mapping' => [
                'cat' => '#/components/schemas/Cat',
                'dog' => 'Dog',
            ],
        ],
    ]);
});

it('always serializes the discriminator property name', function () {
    expect((new Discriminator('petType'))->toArray())
        ->toBe(['propertyName' => 'petType'])
        ->and((new Discriminator(''))->toArray())
        ->toBe(['propertyName' => '']);
});

it('serializes combined types description', function () {
    $type = (new OneOf)
        ->setItems([new StringType, new IntegerType])
        ->setDescription('Wow');

    expect($type->toArray())->toBe([
        'description' => 'Wow',
        'oneOf' => [
            ['type' => 'string'],
            ['type' => 'integer'],
        ],
    ]);
});

it('clones the items and the discriminator', function () {
    $type = (new OneOf)
        ->setItems([new StringType])
        ->setDiscriminator($discriminator = new Discriminator('petType'));

    $clone = $type->clone();
    $clone->items[0]->format('date-time');
    $clone->discriminator->propertyName = 'type';

    expect($type->items[0]->format)->toBe('')
        ->and($discriminator->propertyName)->toBe('petType');
});

it('does not allow non type items', function () {
    (new OneOf)->setItems(['string']);
})->throws(\InvalidArgumentException::class);
