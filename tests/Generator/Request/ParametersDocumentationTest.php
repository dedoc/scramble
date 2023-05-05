<?php

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Route as RouteFacade;

if (trait_exists(HasUuids::class)) {
    it('documents model keys uuid parameters as uuids', function () {
        $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test/{model}', [
            DocumentsModelKeysUuidParametersAsUuids_Test::class, 'index',
        ]));

        expect($params = $openApiDocument['paths']['/test/{model}']['get']['parameters'])
            ->toHaveCount(1)
            ->and($params[0])
            ->toMatchArray([
                'name' => 'model',
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'format' => 'uuid',
                ],
            ]);
    });

    class DocumentsModelKeysUuidParametersAsUuids_Test
    {
        public function index(DocumentsModelKeysUuidParametersAsUuids_Model $model)
        {
            return response()->json();
        }
    }

    class DocumentsModelKeysUuidParametersAsUuids_Model extends \Illuminate\Database\Eloquent\Model
    {
        use HasUuids;
    }
}
