<?php

use Dedoc\Scramble\Tests\stubs\Generator\Bar;
use Illuminate\Support\Facades\Route as RouteFacade;

it('generates response for contollers method', function () {
    $docs = generateForRoute(function () {
        return RouteFacade::get('api/test', ResponseExtensionTest_ControllerWithOtherClassReference::class);
    });
    dd($docs);
});

class ResponseExtensionTest_ControllerWithOtherClassReference
{
    public function __invoke()
    {
        return (new Bar)->getResponse();
    }
}

class Foo
{
    public function getResponse()
    {
        return (new Bar)->getResponse();
    }
}
