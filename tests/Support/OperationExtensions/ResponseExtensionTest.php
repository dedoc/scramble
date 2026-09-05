<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route as RouteFacade;

it('extracts response from `@response` tag', function () {
    $openApiDocument = generateForRoute(function () {
        return RouteFacade::get('api/test', [Foo_ResponseExtensionTest_Controller::class, 'foo']);
    });

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema'])
        ->toHaveKey('type', 'object')
        ->toHaveKey('properties.foo.type', 'string')
        ->toHaveKey('properties.foo.const', 'bar');
});
class Foo_ResponseExtensionTest_Controller
{
    /**
     * @response array{"foo": "bar"}
     */
    public function foo()
    {
        return 42;
    }
}

it('ignores annotation when return node is manually annotated', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', [Foo_ResponseExtensionAnnotationTest__Controller::class, 'foo']));

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema'])
        ->toHaveKey('type', 'object')
        ->toHaveKey('properties.foo.type', 'string');
});
class Foo_ResponseExtensionAnnotationTest__Controller
{
    public function foo(): int
    {
        /**
         * @body array{"foo": "bar"}
         */
        return unknown();
    }
}

it('combines responses with different content types', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', MultipleMimes_ResponseExtensionTest_Controller::class));

    expect($response = $openApiDocument['paths']['/test']['get']['responses'][200])
        ->not->toBeNull()
        ->and($response['headers'])->toHaveKey('Content-Disposition')
        ->and($response['headers']['Content-Disposition'])->not->toHaveKey('required')
        ->and($response['content'])->toBe([
            'application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']],
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'foo' => ['type' => 'string', 'const' => 'bar'],
                    ],
                    'required' => ['foo'],
                ],
            ],
        ]);
});
class MultipleMimes_ResponseExtensionTest_Controller
{
    public function __invoke()
    {
        if (foobar()) {
            return ['foo' => 'bar'];
        }

        return response()->download('data.pdf');
    }
}

it('marks streamed response headers optional when another same-status response is not streamed', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', PartiallyStreamed_ResponseExtensionTest_Controller::class));

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['headers']['Transfer-Encoding'])
        ->not->toHaveKey('required');
});
class PartiallyStreamed_ResponseExtensionTest_Controller
{
    public function __invoke()
    {
        if (foobar()) {
            return response()->stream(fn () => null);
        }

        return response()->json(['foo' => 'bar']);
    }
}

it('documents responses with union type hint', function () {
    $openApiDocument = generateForRoute(fn () => RouteFacade::get('api/test', UnionTypeHint_ResponseExtensionTest_Controller::class));

    expect($responses = $openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveKeys([200, 419])
        ->and($responses[200]['content']['application/json']['schema']['type'])->toBe('object')
        ->and($responses[419]['content']['application/json']['schema']['type'])->toBe('array');
});
class UnionTypeHint_ResponseExtensionTest_Controller
{
    public function __invoke(): Resource_ResponseExtensionTest|JsonResponse
    {
        if (foobar()) {
            return new Resource_ResponseExtensionTest;
        }

        return response()->json([], 419);
    }
}
class Resource_ResponseExtensionTest extends JsonResource
{
    public function toArray(\Illuminate\Http\Request $request)
    {
        return ['id' => 42];
    }
}

it('ignores a plain comment right above the return statement', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', ReturnCommentController_ResponseTest::class));

    expect($responses = $openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveCount(1)
        ->and($responses[200]['description'])
        ->toBe('');
});
class ReturnCommentController_ResponseTest
{
    public function __invoke()
    {
        // This description comes from a comment
        return something_unknown();
    }
}

it('ignores a plain docblock right above the return statement', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', ReturnDocblockController_ResponseTest::class));

    expect($responses = $openApiDocument['paths']['/test']['get']['responses'])
        ->toHaveCount(1)
        ->and($responses[200]['description'])
        ->toBe('');
});
class ReturnDocblockController_ResponseTest
{
    public function __invoke()
    {
        /** This description comes from a docblock. */
        return something_unknown();
    }
}

it('uses explicit inline response documentation and infers missing fields', function (string $method, array $expected) {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ExplicitReturnDocumentationController_ResponseTest::class, $method]));
    $responses = $openApiDocument['paths']['/test']['get']['responses'];

    expect($responses)->toHaveKey($expected['status']);
    $response = $responses[$expected['status']];

    expect($response['description'])->toBe($expected['description'])
        ->and($response['content']['application/json']['schema'])->toMatchArray($expected['schema']);
})->with([
    'description only' => ['descriptionOnly', [
        'status' => 202,
        'description' => 'User profile.',
        'schema' => ['properties' => ['inferred' => ['type' => 'string', 'const' => 'value']]],
    ]],
    'status with prose' => ['statusWithProse', [
        'status' => 201,
        'description' => 'User profile.',
        'schema' => ['properties' => ['inferred' => ['type' => 'string', 'const' => 'value']]],
    ]],
    'body with prose' => ['bodyWithProse', [
        'status' => 202,
        'description' => 'User profile.',
        'schema' => ['properties' => ['id' => ['type' => 'integer']]],
    ]],
    'description overrides prose' => ['descriptionOverridesProse', [
        'status' => 202,
        'description' => 'Explicit description.',
        'schema' => ['properties' => ['inferred' => ['type' => 'string', 'const' => 'value']]],
    ]],
    'all annotations' => ['allAnnotations', [
        'status' => 201,
        'description' => 'User created.',
        'schema' => ['properties' => ['id' => ['type' => 'integer']]],
    ]],
]);

it('does not treat var as inline response documentation', function () {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ExplicitReturnDocumentationController_ResponseTest::class, 'varOnly']));
    $response = $openApiDocument['paths']['/test']['get']['responses'][202];

    expect($response['description'])->toBe('')
        ->and($response['content']['application/json']['schema'])
        ->toMatchArray(['properties' => ['inferred' => ['type' => 'string', 'const' => 'value']]]);
});

it('uses the response description placeholder in inline documentation', function (string $method, string $description) {
    $openApiDocument = generateForRoute(fn () => Route::get('api/test', [ExplicitReturnDocumentationController_ResponseTest::class, $method]));

    expect($openApiDocument['paths']['/test']['get']['responses'][204]['description'])->toBe($description);
})->with([
    'description replaces the inferred description' => ['descriptionWithoutPlaceholder', 'Processed.'],
    'description placeholder preserves the inferred description' => ['descriptionWithPlaceholder', 'Processed. No content'],
    'prose placeholder preserves the inferred description' => ['proseWithPlaceholder', "Processed.\n\nNo content"],
]);

class ExplicitReturnDocumentationController_ResponseTest
{
    public function descriptionOnly()
    {
        /** @description User profile. */
        return response()->json(['inferred' => 'value'], 202);
    }

    public function statusWithProse()
    {
        /**
         * User profile.
         *
         * @status 201
         */
        return response()->json(['inferred' => 'value'], 202);
    }

    public function bodyWithProse()
    {
        /**
         * User profile.
         *
         * @body array{id: int}
         */
        return response()->json(['inferred' => 'value'], 202);
    }

    public function descriptionOverridesProse()
    {
        /**
         * Ignored prose.
         *
         * @description Explicit description.
         */
        return response()->json(['inferred' => 'value'], 202);
    }

    public function varOnly()
    {
        /**
         * Ignored prose.
         *
         * @var array{id: int}
         */
        return response()->json(['inferred' => 'value'], 202);
    }

    public function allAnnotations()
    {
        /**
         * Ignored prose.
         *
         * @description User created.
         *
         * @status 201
         *
         * @body array{id: int}
         */
        return something_unknown();
    }

    public function descriptionWithoutPlaceholder()
    {
        /** @description Processed. */
        return response()->noContent();
    }

    public function descriptionWithPlaceholder()
    {
        /** @description Processed. $0 */
        return response()->noContent();
    }

    public function proseWithPlaceholder()
    {
        /**
         * Processed.
         *
         * $0
         *
         * @status 204
         */
        return response()->noContent();
    }
}
