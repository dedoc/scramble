<?php

namespace Dedoc\Scramble\Tests\Infer\Query;

use Dedoc\Scramble\Infer\Query\CallSite;
use Illuminate\Http\Request;

test('keeps consecutive calls on the same request', function (int $depth) {
    $calls = interproceduralQuery([Calls_CallQueryTest::class, 'requestParameters'])
        ->calls()
        ->on(Request::class)
        ->get(depth: $depth);

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(['integer', 'float', 'boolean', 'string']);
})->with([0, 1]);

test('filters local and nested calls by receiver type', function (string $class) {
    $calls = interproceduralQuery([Calls_CallQueryTest::class, 'handle'])
        ->calls()
        ->on($class)
        ->get();

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(['read', 'make', 'read'])
        ->and($calls->map(fn (CallSite $call) => $call->scope()->context->functionDefinition->type->name)->all())
        ->toBe(['handle', 'handle', 'helper']);
})->with([
    'exact class' => [ChildTarget_CallQueryTest::class],
    'parent class' => [Target_CallQueryTest::class],
    'interface' => [TargetContract_CallQueryTest::class],
]);

test('returns no calls when the receiver type does not match', function () {
    $calls = interproceduralQuery([Calls_CallQueryTest::class, 'handle'])
        ->calls()
        ->on(\stdClass::class)
        ->get();

    expect($calls)->toBeEmpty();
});

test('filters only endpoint calls at depth zero', function () {
    $calls = interproceduralQuery([Calls_CallQueryTest::class, 'handle'])
        ->calls()
        ->on(Target_CallQueryTest::class)
        ->get(depth: 0);

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(['read', 'make']);
});

interface TargetContract_CallQueryTest {}

class Target_CallQueryTest implements TargetContract_CallQueryTest
{
    public function read(): void
    {
        // No calls.
    }

    public static function make(): void
    {
        // No calls.
    }
}

class ChildTarget_CallQueryTest extends Target_CallQueryTest {}

class Calls_CallQueryTest
{
    public function requestParameters(Request $request): void
    {
        $count = $request->integer('count', 10);
        $request->float('weight', 0.5);
        $request->boolean('is_foo');
        $request->string('name', 'John Doe');
    }

    public function handle(ChildTarget_CallQueryTest $target, \DateTime $other): void
    {
        $other->format('c');
        strlen('hello');
        $target?->read();
        ChildTarget_CallQueryTest::make();
        $this->helper($target);
    }

    public function helper(ChildTarget_CallQueryTest $target): void
    {
        $target->read();
    }
}
