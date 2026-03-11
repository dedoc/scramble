<?php

namespace Dedoc\Scramble\Tests\DocumentTransformers;

use Dedoc\Scramble\Tests\Files\SampleUserModel;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('doesnt cause failure of response serialization when reference is removed (#911)', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('test/{user}', CleanupUnusedResponseReferencesTransformerTest_ControllerA::class));

    expect($openApiDocument['paths']['/test/{user}']['get']['responses'])->toHaveKeys([200, 404]);
});
class CleanupUnusedResponseReferencesTransformerTest_ControllerA
{
    /**
     * @throws NotFoundHttpException
     */
    public function __invoke(SampleUserModel $user) {}
}
