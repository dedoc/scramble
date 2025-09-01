<?php

namespace Dedoc\Scramble\Tests\InferExtensions;

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Scramble;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

beforeEach(function () {
    Scramble::infer()
        ->configure()
        ->buildDefinitionsUsingReflectionFor([
            Bar_JsonResourceInferenceTest::class,
        ]);
});

it('infers json resource creation', function ($expression, $expectedType) {
    expect(getStatementType($expression)->toString())->toBe($expectedType);
})->with([
    [
        'new '.Foo_JsonResourceInferenceTest::class.'(42)',
        Foo_JsonResourceInferenceTest::class.'<int(42)>',
    ],
    [
        '(new '.Foo_JsonResourceInferenceTest::class.'(42))->additional(1)',
        Foo_JsonResourceInferenceTest::class.'<int(42), int(1)>',
    ],
    [
        '(new '.ManualConstructCall_JsonResourceInferenceTest::class.'(42))',
        ManualConstructCall_JsonResourceInferenceTest::class.'<int(42)>',
    ],
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

it('infers static collection creation', function ($expression, $expectedType) {
    expect(getStatementType($expression)->toString())->toBe($expectedType);
})->with([
    [
        Foo_JsonResourceInferenceTest::class.'::newCollection([])',
        AnonymousResourceCollection::class.'<list{}, array<mixed>, '.Foo_JsonResourceInferenceTest::class.'>',
    ],
    [
        Foo_JsonResourceInferenceTest::class.'::collection([])',
        AnonymousResourceCollection::class.'<list{}, array<mixed>, '.Foo_JsonResourceInferenceTest::class.'>',
    ],
    [
        OverridenNewCollection_JsonResourceInferenceTest::class.'::collection([])',
        NoCollectedResourcCollection_JsonResourceInferenceTest::class.'<list{}>',
    ],
]);
class OverridenNewCollection_JsonResourceInferenceTest extends JsonResource
{
    public static function newCollection($resource)
    {
        return new NoCollectedResourcCollection_JsonResourceInferenceTest($resource);
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
        FooCollection_JsonResourceInferenceTest::class.'<list{}, array<mixed>, '.Foo_JsonResourceInferenceTest::class.'>',
    ],
]);
class NoCollectedResourcCollection_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\ResourceCollection {}
class FooCollection_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\ResourceCollection
{
    public $collects = Foo_JsonResourceInferenceTest::class;
}

it('infers resource collection toArray', function ($expression, $expectedType) {
    expect(getStatementType($expression)->toString())->toBe($expectedType);
})->with([
    // collection with $collects, without toArray
    [
        '(new '.FooCollection_JsonResourceInferenceTest::class.'([]))->toArray()',
        'array<'.Foo_JsonResourceInferenceTest::class.'>',
    ],
    // collection with $collects, with parent::toArray()
    [
        '(new '.ParentToArrayCollection_JsonResourceInferenceTest::class.'([]))->toArray()',
        'array<'.Foo_JsonResourceInferenceTest::class.'>',
    ],
    // collection with $collects, with call to `collection` property
    [
        '(new '.CallToCollection_JsonResourceInferenceTest::class.'([]))->toArray()',
        'array{data: '.Collection::class.'<int, '.Foo_JsonResourceInferenceTest::class.'>, links: array{self: string(link-value)}}',
    ],
]);
it('gives me understanding', function () {
    $cd = (new ClassAnalyzer(app(Index::class)))->analyze(ParentToArrayCollection_JsonResourceInferenceTest::class);

    $md = $cd->getMethod('toArray');

    expect($md->type->getReturnType()->toString())->toBe('array<TCollects>');
});
class ParentToArrayCollection_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\ResourceCollection
{
    public $collects = Foo_JsonResourceInferenceTest::class;

    public function toArray(Request $request)
    {
        return parent::toArray($request);
    }
}
class CallToCollection_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\ResourceCollection
{
    public $collects = Foo_JsonResourceInferenceTest::class;

    public function toArray(Request $request)
    {
        return [
            'data' => $this->collection,
            'links' => [
                'self' => 'link-value',
            ],
        ];
    }
}

it('infers anonymous collection creation', function ($expression, $expectedType) {
    expect(getStatementType($expression)->toString())->toBe($expectedType);
})->with([
    [
        'new '.AnonymousResourceCollection::class.'([], '.Bar_JsonResourceInferenceTest::class.'::class)',
        AnonymousResourceCollection::class.'<list{}, array<mixed>, '.Bar_JsonResourceInferenceTest::class.'>',
    ],
    [
        FooAnonCollection_JsonResourceInferenceTest::class.'::collection([])',
        AnonymousResourceCollection::class.'<list{}, array<mixed>, '.FooAnonCollection_JsonResourceInferenceTest::class.'>',
    ],
    [
        FooAnonCollectionTap_JsonResourceInferenceTest::class.'::collection([])',
        AnonymousResourceCollection::class.'<list{}, array<mixed>, '.FooAnonCollectionTap_JsonResourceInferenceTest::class.'>',
    ],
]);
class FooAnonCollection_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\JsonResource
{
    public static function collection($resource)
    {
        return new AnonymousResourceCollection($resource, static::class);
    }
}
class FooAnonCollectionTap_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\JsonResource
{
    public static function collection($resource)
    {
        return tap(new AnonymousResourceCollection($resource, static::class), function ($v) {});
    }
}

it('infers anonymous resource collection toArray', function ($expression, $expectedType) {
    expect(getStatementType($expression)->toString())->toBe($expectedType);
})->with([
    [
        Foo_JsonResourceInferenceTest::class.'::collection([])->toArray()',
        'array<'.Foo_JsonResourceInferenceTest::class.'>',
    ],
]);

it('falls back to parent resource collection toArray if cannot infer overwritten type', function ($expression, $expectedType) {
    expect(getStatementType($expression)->toString())->toBe($expectedType);
})->with([
    [
        '(new '.OverwrittenUnknownCollection_JsonResourceInferenceTest::class.'([], '.Foo_JsonResourceInferenceTest::class.'::class))->toArray()',
        'array<'.Foo_JsonResourceInferenceTest::class.'<unknown>>',
    ],
]);
class OverwrittenUnknownCollection_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\AnonymousResourceCollection
{
    public function toArray($request)
    {
        return unknown();
    }
}

it('handles that weird case', function () {
    $ca = new ClassAnalyzer($index = app(Index::class));

    $b = $ca->analyze(BaloobooFooAnonCollectionTap_JsonResourceInferenceTest::class);
    $c = $ca->analyze(CarFooAnonCollectionTap_JsonResourceInferenceTest::class);

    $cd = $c->getMethodDefinition('get');
    $bd = $b->getMethodDefinition('get');

    $rt1 = $cd->type->getReturnType();
    $rt2 = $bd->type->getReturnType();

    expect($rt1->toString())->toBe('class-string<'.CarFooAnonCollectionTap_JsonResourceInferenceTest::class.'>')
        ->and($rt2->toString())->toBe('class-string<'.BaloobooFooAnonCollectionTap_JsonResourceInferenceTest::class.'>');
});
class Static_JsonResourceInferenceTest
{
    public static function get()
    {
        return static::class;
    }
}
class BaloobooFooAnonCollectionTap_JsonResourceInferenceTest extends Static_JsonResourceInferenceTest {}
class CarFooAnonCollectionTap_JsonResourceInferenceTest extends Static_JsonResourceInferenceTest {}

it('handles that second weird case', function () {
    $ca = new ClassAnalyzer($index = app(Index::class));

    $b = $ca->analyze(Bar_JsonResourceInferenceTest::class);
    $j = $ca->analyze(Jar_JsonResourceInferenceTest::class);

    $bget = $b->getMethodDefinition('collection');
    $jget = $j->getMethodDefinition('collection');

    $bgetret = $bget->type->getReturnType();
    $jgetret = $jget->type->getReturnType();

    expect($bgetret->toString())
        ->toBe(
            AnonymousResourceCollection::class.'<TResource1, array<mixed>, '.Bar_JsonResourceInferenceTest::class.'>')
        ->and($jgetret->toString())
        ->toBe(
            AnonymousResourceCollection::class.'<TResource1, array<mixed>, '.Jar_JsonResourceInferenceTest::class.'>'
        );
});
class Jar_JsonResourceInferenceTest extends \Illuminate\Http\Resources\Json\JsonResource {}
