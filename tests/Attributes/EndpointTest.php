<?php

namespace Dedoc\Scramble\Tests\Attributes;

use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Support\Facades\Route;

it('attaches operation ID to controller action', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', AController_EndpointTest::class));

    expect($openApiDocument['paths']['/test']['get']['operationId'])
        ->toBe('do_something_magic');
});
class AController_EndpointTest
{
    #[Endpoint(operationId: 'do_something_magic')]
    public function __invoke()
    {
        return something_unknown();
    }
}
