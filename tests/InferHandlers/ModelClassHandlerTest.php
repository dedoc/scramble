<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\InferHandlers\ModelClassHandler;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Tests\Files\SamplePostModelWithToArray;
use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->infer = new Infer(new Infer\Scope\Index());
});

it('adds models attributes to the model class definition as properties', function () {
    $definition = $this->infer->analyzeClass(SamplePostModelWithToArray::class);

    expect($definition->properties)
        ->toHaveKeys([
            /* Attributes from the DB */
            'id', 'status', 'user_id', 'title', 'body', 'created_at', 'updated_at',
            /* Appended attributes */
            'read_time',
            /* Relations */
            'parent', 'children', 'user',
            /* other properties from model class are ommited here but exist on type */
        ])
        ->and(collect($definition->properties)->map(fn ($p) => Str::replace('Dedoc\\Scramble\\Tests\\Files\\', '', $p->type->toString())))
        ->toMatchArray([
            'id' => 'int',
            'status' => 'Status',
            'user_id' => 'int',
            'title' => 'string',
            'body' => 'string',
            'created_at' => 'null|\Carbon\Carbon',
            'updated_at' => 'null|\Carbon\Carbon',
            'read_time' => 'unknown',
            'user' => 'SampleUserModel',
            'parent' => 'SamplePostModelWithToArray',
            'children' => 'Illuminate\Database\Eloquent\Collection<SamplePostModelWithToArray>',
        ]);
});

it('adds toArray method type the model class without defined toArray class', function () {
    $definition = $this->infer->analyzeClass(SampleUserModel::class);

    $toArrayReturnType = $definition->methods['toArray']->type->getReturnType();

    expect(collect($toArrayReturnType->items)->mapWithKeys(fn (ArrayItemType_ $t) => [$t->key.($t->isOptional ? '?' : '') => $t->value->toString()]))
        ->toMatchArray([
            'id' => 'int',
            'name' => 'string',
            'email' => 'string',
            'password' => 'string',
            'remember_token' => 'null|string',
            'created_at' => 'null|string',
            'updated_at' => 'null|string',
        ]);
});
