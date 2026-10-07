<?php

namespace Dedoc\Scramble\Tests\OpenApi;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Discriminator;

it('serializes a discriminator for the target version', function (OpenApiVersion $version, array $expected) {
    $discriminator = (new Discriminator('petType', ['dog' => '#/components/schemas/Dog']))
        ->setDefaultMapping('#/components/schemas/OtherPet');

    expect(serializeAsVersion($discriminator, $version))->toEqual($expected);
})->with([
    [OpenApiVersion::V3_1, [
        'propertyName' => 'petType',
        'mapping' => (object) ['dog' => '#/components/schemas/Dog'],
    ]],
    [OpenApiVersion::V3_2, [
        'propertyName' => 'petType',
        'mapping' => (object) ['dog' => '#/components/schemas/Dog'],
        'defaultMapping' => '#/components/schemas/OtherPet',
    ]],
]);
