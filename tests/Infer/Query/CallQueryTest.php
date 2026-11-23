<?php

namespace Dedoc\Scramble\Tests\Infer\Query;

use Dedoc\Scramble\Infer\Query\CallSite;
use Dedoc\Scramble\Support\Type\Literal\LiteralFloatType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\FuncCall;

test('filters calls without a receiver constraint', function () {
    $calls = interproceduralQuery([Calls_CallQueryTest::class, 'handle'])
        ->calls()
        ->where(fn (CallSite $call) => $call->node() instanceof FuncCall)
        ->get(depth: 0);

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(['strlen']);
});

test('filters target calls in direct callees', function () {
    $calls = interproceduralQuery([Calls_CallQueryTest::class, 'handle'])
        ->calls()
        ->to([Target_CallQueryTest::class, 'read'])
        ->where(fn (CallSite $call) => $call->scope()->context->functionDefinition->type->name === 'helper')
        ->get();

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(['read'])
        ->and($calls[0]->scope()->context->functionDefinition->type->name)->toBe('helper');
});

test('requires all call predicates to match', function () {
    $calls = interproceduralQuery([Calls_CallQueryTest::class, 'requestParameters'])
        ->calls()
        ->on(Request::class)
        ->where(function (CallSite $call) {
            $default = $call->arguments()->get('default', 1);

            return $default instanceof LiteralIntegerType || $default instanceof LiteralFloatType;
        })
        ->where(fn (CallSite $call) => $call->node()->name->toString() !== 'integer')
        ->get(depth: 0);

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(['float']);
});

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

test('filters calls by target class and method', function (int $depth, array $expectedScopes) {
    $calls = interproceduralQuery([Calls_CallQueryTest::class, 'handle'])
        ->calls()
        ->to([Target_CallQueryTest::class, 'READ'])
        ->get(depth: $depth);

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(array_fill(0, count($expectedScopes), 'read'))
        ->and($calls->map(fn (CallSite $call) => $call->scope()->context->functionDefinition->type->name)->all())
        ->toBe($expectedScopes);
})->with([
    'endpoint only' => [0, ['handle']],
    'direct callees' => [1, ['handle', 'helper']],
]);

test('matches static calls through a target interface', function () {
    $calls = interproceduralQuery([Calls_CallQueryTest::class, 'handle'])
        ->calls()
        ->to([TargetContract_CallQueryTest::class, 'make'])
        ->get();

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(['make']);
});

test('returns no calls when the target does not match', function (array $target) {
    $calls = interproceduralQuery([Calls_CallQueryTest::class, 'handle'])
        ->calls()
        ->to($target)
        ->get();

    expect($calls)->toBeEmpty();
})->with([
    'different class' => [[\stdClass::class, 'read']],
    'different method' => [[Target_CallQueryTest::class, 'missing']],
]);

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
