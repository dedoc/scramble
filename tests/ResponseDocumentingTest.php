<?php

use Illuminate\Routing\Route;
use function Spatie\Snapshots\assertMatchesSnapshot;

test('response()->noContent() call support', function () {
    \Illuminate\Support\Facades\Route::get('test', [Foo_Test::class, 'index']);

    \Dedoc\Scramble\Scramble::routes(fn (Route $r) => $r->uri === 'test');
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
    \Illuminate\Support\Facades\Route::get('test', [Foo_TestTwo::class, 'index']);

    \Dedoc\Scramble\Scramble::routes(fn (Route $r) => $r->uri === 'test');
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
    \Illuminate\Support\Facades\Route::get('test', [Foo_TestThree::class, 'index']);

    \Dedoc\Scramble\Scramble::routes(fn (Route $r) => $r->uri === 'test');
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
