<?php

namespace Dedoc\Scramble\Tests\Support;

use Dedoc\Scramble\Infer\Reflector\MethodReflector;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('marks only route action request parameters and exposes provenance in call arguments', function () {
    $route = Route::post('test', [Controller_RouteInfoTest::class, 'handle']);
    $definition = (new RouteInfo($route, 'POST'))->getActionDefinition();

    expect($definition->type->arguments['request']->getAttribute('mayContainRequestInput'))->toBeTrue()
        ->and($definition->type->arguments['form']->getAttribute('mayContainRequestInput'))->toBeTrue()
        ->and($definition->type->arguments['name']->hasAttribute('mayContainRequestInput'))->toBeFalse();

    $calls = $definition->query()->calls()->to([Controller_RouteInfoTest::class, 'consume'])->get(depth: 0);

    expect($calls[0]->arguments()->get('request', 0)->getAttribute('mayContainRequestInput'))->toBeTrue();

    $helper = MethodReflector::make(Controller_RouteInfoTest::class, 'consume')->getFunctionLikeDefinition();

    expect($helper->type->arguments['request']->hasAttribute('mayContainRequestInput'))->toBeFalse();
});

class Controller_RouteInfoTest
{
    public function handle(Request $request, FormRequest $form, string $name): void
    {
        self::consume($request);
    }

    public static function consume(Request $request): Request
    {
        return $request;
    }
}
