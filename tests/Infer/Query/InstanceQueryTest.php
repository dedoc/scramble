<?php

namespace Dedoc\Scramble\Tests\Infer\Query;

use Dedoc\Scramble\Infer\Query\TrackedInstance;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;

test('finds constructed and factory instances at the requested depth', function (int $depth, array $expected) {
    $instances = interproceduralQuery([Instances_InstanceQueryTest::class, 'handle'])
        ->instancesOf(Target_InstanceQueryTest::class)
        ->get(depth: $depth);

    expect($instances->map(fn (TrackedInstance $instance) => $instance->type()->name)->all())
        ->toBe($expected)
        ->and($instances->map(fn (TrackedInstance $instance) => $instance->origin()->node()::class)->all())
        ->toBe($depth === 0 ? [New_::class, StaticCall::class] : [New_::class, StaticCall::class, New_::class]);
})->with([
    'endpoint only' => [0, [ChildTarget_InstanceQueryTest::class, Target_InstanceQueryTest::class]],
    'direct callees' => [1, [ChildTarget_InstanceQueryTest::class, Target_InstanceQueryTest::class, ChildTarget_InstanceQueryTest::class]],
]);

test('filters instances by their origin', function () {
    $instances = interproceduralQuery([Instances_InstanceQueryTest::class, 'handle'])
        ->instancesOf(Target_InstanceQueryTest::class)
        ->where(fn (TrackedInstance $instance) => $instance->origin()->node() instanceof StaticCall)
        ->get();

    expect($instances->map(fn (TrackedInstance $instance) => $instance->type()->name)->all())
        ->toBe([Target_InstanceQueryTest::class]);
});

test('returns no instances when no type matches', function () {
    $instances = interproceduralQuery([Instances_InstanceQueryTest::class, 'handle'])
        ->instancesOf(\DateTime::class)
        ->get();

    expect($instances)->toBeEmpty();
});

class Target_InstanceQueryTest
{
    public static function make(): self
    {
        return new self;
    }
}

class ChildTarget_InstanceQueryTest extends Target_InstanceQueryTest {}

class Instances_InstanceQueryTest
{
    public function handle(): void
    {
        new ChildTarget_InstanceQueryTest;
        Target_InstanceQueryTest::make();
        Target_InstanceQueryTest::make(...);
        strlen('hello');
        $this->helper();
    }

    public function helper(): void
    {
        new ChildTarget_InstanceQueryTest;
    }
}
