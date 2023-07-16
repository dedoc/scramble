<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Tests\Files\SamplePostModelWithToArray;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->infer = new Infer(new Infer\Scope\Index());
});

it('adds models attributes to the model class definition as properties', function () {
    $this->infer->analyzeClass(SamplePostModelWithToArray::class);

    $object = new ObjectType(SamplePostModelWithToArray::class);

    $expectedPropertiesTypes = [
        /* Attributes from the DB */
        'id' => 'int',
        'status' => 'Status',
        'user_id' => 'int',
        'title' => 'string',
        'body' => 'string',
        'created_at' => 'Carbon\Carbon|null',
        'updated_at' => 'Carbon\Carbon|null',
        /* Appended attributes */
        'read_time' => 'unknown',
        /* Relations */
        'user' => 'SampleUserModel',
        'parent' => 'SamplePostModelWithToArray',
        'children' => 'Illuminate\Database\Eloquent\Collection<SamplePostModelWithToArray>',
        /* other properties from model class are ommited here but exist on type */
    ];

    foreach ($expectedPropertiesTypes as $name => $type) {
        $propertyType = $object->getPropertyType($name);

        expect(Str::replace('Dedoc\\Scramble\\Tests\\Files\\', '', $propertyType->toString()))
            ->toBe($type);
    }
});

it('adds toArray method type the model class without defined toArray class', function () {
    $this->infer->analyzeClass(SampleUserModel::class);

    $toArrayReturnType = (new ObjectType(SampleUserModel::class))
        ->getMethodReturnType('toArray');

    expect(collect($toArrayReturnType->items)->mapWithKeys(fn (ArrayItemType_ $t) => [$t->key.($t->isOptional ? '?' : '') => $t->value->toString()]))
        ->toMatchArray([
            'id' => 'int',
            'name' => 'string',
            'email' => 'string',
            'password' => 'string',
            'remember_token' => 'string|null',
            'created_at' => 'string|null',
            'updated_at' => 'string|null',
        ]);
});
