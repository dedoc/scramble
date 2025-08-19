<?php

namespace Dedoc\Scramble\Tests\InferExtensions;

use Dedoc\Scramble\Infer\Scope\Index;

beforeEach(function () {
    Index::$avoidAnalyzingAstClasses[] = Bar_JsonResourceInferenceTest::class;
});

it('infers json resource creation', function ($expression, $expectedType) {
    expect(getStatementType($expression)->toString())->toBe($expectedType);
})->with([
//    [
//        'new '.Foo_JsonResourceInferenceTest::class.'(42)',
//        Foo_JsonResourceInferenceTest::class.'<int(42)>',
//    ],
//    [
//        '(new '.Foo_JsonResourceInferenceTest::class.'(42))->additional(1)',
//        Foo_JsonResourceInferenceTest::class.'<int(42), int(1)>',
//    ],
//    [
//        '(new '.ManualConstructCall_JsonResourceInferenceTest::class.'(42))',
//        ManualConstructCall_JsonResourceInferenceTest::class.'<int(42)>',
//    ],
    [
        '(new '.ManualConstructCallWithData_JsonResourceInferenceTest::class.'(42))',
        ManualConstructCallWithData_JsonResourceInferenceTest::class.'<int(23)>',
    ],
]);
class Bar_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\JsonResource {}
class Foo_JsonResourceInferenceTest extends Bar_JsonResourceInferenceTest {}
class ManualConstructCall_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\JsonResource
{
    public function __construct($resource)
    {
        parent::__construct($resource);
    }
}
class ManualConstructCallWithData_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\JsonResource
{
    public function __construct($resource)
    {
        parent::__construct(23);
    }
}

it('infers resource collection creation', function ($expression, $expectedType) {
    expect(getStatementType($expression)->toString())->toBe($expectedType);
})->with([
    [
        'new '.NoCollectedResourcCollection_JsonResourceInferenceTest::class.'([])',
        NoCollectedResourcCollection_JsonResourceInferenceTest::class.'<list{}>',
    ],
    [
        'new '.FooCollection_JsonResourceInferenceTest::class.'([])',
        FooCollection_JsonResourceInferenceTest::class.'<list{}, array<mixed>, string('.Foo_JsonResourceInferenceTest::class.')>',
    ],
]);
class NoCollectedResourcCollection_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\ResourceCollection {}
class FooCollection_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\ResourceCollection
{
    public $collects = Foo_JsonResourceInferenceTest::class;
}
