<?php

namespace Dedoc\Scramble\Tests\Infer\Query;

use Dedoc\Scramble\Infer\Query\CallSite;

test('lists calls in the current function body with their scope', function () {
    $query = interproceduralQuery([Calls_InterproceduralQueryTest::class, 'run']);
    $calls = $query->calls()->get();

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(['strlen', 'trim']);

    foreach ($calls as $call) {
        expect($call->scope())->toBe($query->definition->getScope());
    }
});

test('returns an empty collection for a function without calls', function () {
    expect(interproceduralQuery([Calls_InterproceduralQueryTest::class, 'emptyBody'])->calls()->get())->toBeEmpty();
});

test('lists calls from a directly called method with the callee scope', function () {
    $query = interproceduralQuery([Calls_InterproceduralQueryTest::class, 'handle']);
    $calls = $query->calls()->get();

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(['run', 'strlen', 'trim'])
        ->and($calls[0]->scope())->toBe($query->definition->getScope())
        ->and($calls[1]->scope()->context->functionDefinition->type->name)->toBe('run')
        ->and($calls[2]->scope())->toBe($calls[1]->scope());
});

test('stops call discovery after one level', function () {
    $calls = interproceduralQuery([CallsDepth_InterproceduralQueryTest::class, 'outer'])->calls()->get(depth: 1);

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(['handle', 'run']);
});

test('lists only endpoint calls at depth zero', function () {
    $calls = interproceduralQuery([Calls_InterproceduralQueryTest::class, 'handle'])->calls()->get(depth: 0);

    expect($calls->map(fn (CallSite $call) => $call->node()->name->toString())->all())
        ->toBe(['run']);
});

test('rejects unsupported call query depths', function (int $depth) {
    $query = interproceduralQuery([Calls_InterproceduralQueryTest::class, 'emptyBody'])->calls();

    expect(fn () => $query->get(depth: $depth))
        ->toThrow(\InvalidArgumentException::class, 'Call query depth must be 0 or 1.');
})->with([-1, 2]);

class Calls_InterproceduralQueryTest
{
    public function run(): void
    {
        strlen('hello');
        trim(' world ');
    }

    public function emptyBody(): void
    {
        // No calls.
    }

    public function handle(): void
    {
        $this->run();
    }
}

class CallsDepth_InterproceduralQueryTest extends Calls_InterproceduralQueryTest
{
    public function outer(): void
    {
        $this->handle();
    }
}
