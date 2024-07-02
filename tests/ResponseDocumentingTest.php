<?php

use Illuminate\Routing\Route;

use function Spatie\Snapshots\assertMatchesSnapshot;

test('response()->noContent() call support', function () {
    \Illuminate\Support\Facades\Route::get('api/test', [Foo_Test::class, 'index']);

    \Dedoc\Scramble\Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});
class Foo_Test
{
    public function index()
    {
        return response()->noContent();
    }
}

test('response()->json() call support with phpdoc help', function () {
    \Illuminate\Support\Facades\Route::get('api/test', [Foo_TestTwo::class, 'index']);

    \Dedoc\Scramble\Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});
class Foo_TestTwo
{
    public function index()
    {
        return response()->json([
            /** @var array{msg: string, code: int} */
            'error' => $var,
        ], 500);
    }
}

test('multiple responses support', function () {
    \Illuminate\Support\Facades\Route::get('api/test', [Foo_TestThree::class, 'index']);

    \Dedoc\Scramble\Scramble::routes(fn (Route $r) => $r->uri === 'api/test');
    $openApiDocument = app()->make(\Dedoc\Scramble\Generator::class)();

    assertMatchesSnapshot($openApiDocument);
});
class Foo_TestThree
{
    public function index()
    {
        try {
            something_some();
        } catch (\Throwable $e) {
            return response()->json([
                /** @var array{msg: string, code: int} */
                'error' => $var,
            ], 500);
        }
        if ($foo) {
            return response()->json(['foo' => 'one']);
        }

        return response()->json(['foo' => 'bar']);
    }
}

test('manually annotated responses support', function () {
    $openApiDocument = generateForRoute(fn () => \Illuminate\Support\Facades\Route::get('api/test', [Foo_TestFour::class, 'index']));

    assertMatchesSnapshot($openApiDocument);
});
class Foo_TestFour
{
    public function index()
    {
        if ($foo) {
            /**
             * Advanced comment.
             *
             * With more description.
             *
             * @status 201
             *
             * @body array{foo: string}
             */
            return response()->json(['foo' => 'one']);
        }

        // Simple comment.
        return response()->json(['foo' => 'bar']);
    }
}

test('manually annotated responses resources support', function () {
    $openApiDocument = generateForRoute(fn () => \Illuminate\Support\Facades\Route::get('api/test', [Foo_TestFive::class, 'index']));

    expect($openApiDocument['paths']['/test']['get']['responses'][200]['content']['application/json']['schema'])
        ->toBe([
            'type' => 'object',
            'properties' => [
                'data' => ['$ref' => '#/components/schemas/Foo_TestFiveResource'],
            ],
            'required' => ['data'],
        ]);
});
class Foo_TestFive
{
    public function index()
    {
        /**
         * @body Foo_TestFiveResource
         */
        return response()->json(['foo' => 'bar']);
    }
}
class Foo_TestFiveResource extends \Illuminate\Http\Resources\Json\JsonResource
{
    public static $wrap = 'data';

    public function toArray(\Illuminate\Http\Request $request)
    {
        return [
            'foo' => $this->id,
        ];
    }
}

test('automated response status code inference when using ->response->setStatusCode method', function () {
    $openApiDocument = generateForRoute(fn () => \Illuminate\Support\Facades\Route::get('api/test', [Foo_TestSix::class, 'single']));

    expect($openApiDocument['paths']['/test']['get']['responses'][201]['content']['application/json']['schema'])
        ->toBe(['$ref' => '#/components/schemas/Foo_TestFiveResource']);
});

test('automated response status code inference when using collection ->response->setStatusCode method', function () {
    $openApiDocument = generateForRoute(fn () => \Illuminate\Support\Facades\Route::get('api/test', [Foo_TestSix::class, 'collection']));

    expect($openApiDocument['paths']['/test']['get']['responses'][201]['content']['application/json']['schema'])
        ->toBe([
            'type' => 'array',
            'items' => ['$ref' => '#/components/schemas/Foo_TestFiveResource'],
        ]);
});
class Foo_TestSix
{
    public function single()
    {
        return (new Foo_TestFiveResource())->response()->setStatusCode(201);
    }
    public function collection()
    {
        return Foo_TestFiveResource::collection()->response()->setStatusCode(201);
    }
}
