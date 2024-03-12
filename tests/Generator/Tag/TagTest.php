<?php

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route as RouteFacade;

it('documents tags based resolveTagsUsing', function () {

    $openApiDocument = generateForRoute(function () {
        Scramble::resolveTagsUsing(function (RouteInfo $routeInfo) {
            return array_values(array_unique(
                Arr::map($routeInfo->phpDoc()->getTagsByName('@tags'), fn ($tag) => trim($tag?->value?->value))
            ));
        });

        return RouteFacade::get('api/test', [ResolveTagDocumentationTestController::class, 'a'])->name('someNameOfRoute');
    });

    expect($openApiDocument['paths']['/test']['get'])
        ->toHaveKey('tags', ['testTag']);
});
class ResolveTagDocumentationTestController extends \Illuminate\Routing\Controller
{
    /**
     * @tags testTag
     */
    public function a(): Illuminate\Http\Resources\Json\JsonResource
    {
        return $this->unknown_fn();
    }
}
