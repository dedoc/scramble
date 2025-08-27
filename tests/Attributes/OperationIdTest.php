<?php

namespace Dedoc\Scramble\Tests\Attributes;

use Dedoc\Scramble\Attributes\OperationId;
use Illuminate\Support\Facades\Route;

it('attaches operation ID to controller action', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test', AController_OperationTest::class));

    expect($openApiDocument['paths']['/test']['get']['operationId'])
        ->toBe('do_something_magic');
});
class AController_OperationTest
{
    #[OperationId('do_something_magic')]
    public function __invoke()
    {
        return something_unknown();
    }
}
