<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\UnknownType;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;

it('documents the response type when return is not array node', function () {
    $type = new Generic(JsonResourceTypeToSchemaTest_Sample::class, [new UnknownType]);

    $transformer = new TypeTransformer($infer = app(Infer::class), $components = new Components, [
        JsonResourceTypeToSchema::class,
    ]);
    $extension = new JsonResourceTypeToSchema($infer, $transformer, $components);

    $schema = $extension->toSchema($type);

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
        ],
        'required' => ['id', 'name'],
    ]);
});

/**
 * @property JsonResourceTypeToSchemaTest_User $resource
 */
class JsonResourceTypeToSchemaTest_Sample extends \Illuminate\Http\Resources\Json\JsonResource
{
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
class JsonResourceTypeToSchemaTest_User extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'users';

    protected $visible = ['id', 'name'];
}
