<?php

use Dedoc\Scramble\Support\ClassAstHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Stmt\ClassMethod;
use function Spatie\Snapshots\assertMatchesTextSnapshot;

uses(RefreshDatabase::class);

it('gets types', function () {
    $class = new ClassAstHelper(InferTypesTest_SampleClass::class);
    $method = $class->findFirstNode(fn ($node) => $node instanceof ClassMethod);
    dd($method->getAttribute('type')->getReturnType());
})->skip();

it('gets json resource type', function () {
    $class = new ClassAstHelper(InferTypesTest_SampleJsonResource::class);
    $scope = $class->scope;
    $method = $class->findFirstNode(fn ($node) => $node instanceof ClassMethod);

    $returnType = $scope->getType($method)->getReturnType();

    assertMatchesTextSnapshot($returnType->toString());
});

it('simply infers method types', function () {
    $class = new ClassAstHelper(Foo_SampleClass::class);
    $scope = $class->scope;
    $method = $class->findFirstNode(fn ($node) => $node instanceof ClassMethod);

    $returnType = $scope->getType($method)->getReturnType();

    expect($returnType->toString())->toBe('int');
});

it('infers method types for methods declared not in order', function () {
    $class = new ClassAstHelper(FooTwo_SampleClass::class);
    $scope = $class->scope;
    $method = $class->findFirstNode(fn ($node) => $node instanceof ClassMethod);

    $returnType = $scope->getType($method)->getReturnType();

    expect($returnType->toString())->toBe('(): int');
})->only();

it('infers unknown method call', function () {
    $class = new ClassAstHelper(FooThree_SampleClass::class);
    $scope = $class->scope;
    $method = $class->findFirstNode(fn ($node) => $node instanceof ClassMethod);

    $returnType = $scope->getType($method)->getReturnType();

    expect($returnType->toString())->toBe('unknown');
});

class FooThree_SampleClass
{
    public function foo()
    {
        return $this->bar();
    }
}

class FooTwo_SampleClass
{
    public function foo()
    {
        return fn () => $this->bar();
    }

    public function bar()
    {
        return 1;
    }
}

class Foo_SampleClass
{
    public function bar()
    {
        return 1;
    }

    public function foo()
    {
        return $this->bar();
    }
}

class InferTypesTest_SampleClass
{
    public function wow($request)
    {
        return new BrandEdge($request);
    }
}

/**
 * @property InferTypesTest_SampleModel $resource
 */
class InferTypesTest_SampleJsonResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            $this->merge(fn () => ['foo' => 'bar']),
            $this->mergeWhen(true, fn () => ['foo' => 'bar', 'id_inside' => $this->resource->id]),
            'when' => $this->when(true, ['wiw']),
            'item' => new InferTypesTest_SampleTwoJsonResource($this->resource),
            'items' => InferTypesTest_SampleTwoJsonResource::collection($this->resource),
            'optional_when_new' => $this->when(true, fn () => new InferTypesTest_SampleTwoJsonResource($this->resource)),
            $this->mergeWhen(true, fn () => [
                'threads' => [
                    $this->mergeWhen(true, fn () => [
                        'brand' => new InferTypesTest_SampleTwoJsonResource(null),
                    ]),
                ],
            ]),
            '_test' => 1,
            /** @var int $with_doc great */
            'with_doc' => $this->foo,
            /** @var string wow this is good */
            'when_with_doc' => $this->when(true, 'wiw'),
            'some' => $this->some,
            'id' => $this->id,
            'email' => $this->resource->email,
        ];
    }
}

/**
 * @property InferTypesTest_SampleModel $resource
 */
class InferTypesTest_SampleTwoJsonResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'email' => $this->resource->email,
        ];
    }
}

class InferTypesTest_SampleModel extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = true;

    protected $table = 'users';
}
