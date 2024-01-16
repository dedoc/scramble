<?php

use Illuminate\Support\Facades\Route as RouteFacade;

it('deprecated method sets deprecation key', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [Deprecated_ResponseExtensionTest_Controller::class, 'deprecated']);
    });

    expect($openApiDocument['paths']['/test']['get'])
        ->not()->toHaveKey('description')
        ->toHaveKey('deprecated', true);
});
class Deprecated_ResponseExtensionTest_Controller
{
    /**
     * @deprecated
     */
    public function deprecated()
    {
        return false;
    }
}

it('deprecated method sets key and description', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [Deprecated_Description_ResponseExtensionTest_Controller::class, 'deprecated']);
    });

    expect($openApiDocument['paths']['/test']['get'])
        ->toHaveKey('description', 'Deprecation description')
        ->toHaveKey('deprecated', true);
});

class Deprecated_Description_ResponseExtensionTest_Controller
{
    /**
     * @deprecated Deprecation description
     *
     * @response array{ "test": "test"}
     */
    public function deprecated()
    {
        return false;
    }
}

it('deprecated class with description sets keys', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [Deprecated_Class_Description_ResponseExtensionTest_Controller::class, 'deprecated']);
    });

    expect($openApiDocument['paths']['/test']['get'])
        ->toHaveKey('description', 'Class description'."\n\n".'Deprecation description')
        ->toHaveKey('deprecated', true);
});

/** @deprecated Class description */
class Deprecated_Class_Description_ResponseExtensionTest_Controller
{
    /**
     * @deprecated Deprecation description
     *
     * @response array{ "test": "test"}
     */
    public function deprecated()
    {
        return false;
    }
}

it('deprecated class without description sets keys', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [Deprecated_Class_ResponseExtensionTest_Controller::class, 'deprecated']);
    });

    expect($openApiDocument['paths']['/test']['get'])
        ->not()->toHaveKey('description')
        ->toHaveKey('deprecated', true);
});

/** @deprecated */
class Deprecated_Class_ResponseExtensionTest_Controller
{
    /**
     * @response array{ "test": "test"}
     */
    public function deprecated()
    {
        return false;
    }
}

it('not deprecated ignores the class deprecation', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [Not_Deprecated_Class_ResponseExtensionTest_Controller::class, 'notDeprecated']);
    });

    expect($openApiDocument['paths']['/test']['get'])
        ->not()->toHaveKey('description')
        ->not()->toHaveKey('deprecated');
});

/** @deprecated */
class Not_Deprecated_Class_ResponseExtensionTest_Controller
{
    /**
     * @not-deprecated
     */
    public function notDeprecated()
    {
        return false;
    }
}
