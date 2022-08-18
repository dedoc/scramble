<?php

use Dedoc\ApiDocs\Support\ComplexTypeHandler\ComplexTypeHandlers;
use Dedoc\ApiDocs\Support\Type\Identifier;
use Illuminate\Http\Resources\Json\JsonResource;
use function Spatie\Snapshots\assertMatchesSnapshot;

it('gets json resource type', function () {
    $type = ComplexTypeHandlers::handle(new Identifier(ComplexTypeHandlersTest_SampleType::class));

    assertMatchesSnapshot($type->toArray());
});

class ComplexTypeHandlersTest_SampleType extends JsonResource
{
    public function toArray($request)
    {
        return [
            'foo' => 1,
            $this->mergeWhen(true, [
                'hey' => 'ho',
            ]),
            $this->merge([
                'bar' => 'foo',
            ]),
        ];
    }
}
