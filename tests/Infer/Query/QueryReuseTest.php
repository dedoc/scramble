<?php

namespace Dedoc\Scramble\Tests\Infer\Query;

use Dedoc\Scramble\Infer\Query\CallSite;
use Dedoc\Scramble\Infer\Query\TrackedInstance;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;

test('repeated helpers keep all occurrences and their argument scopes', function () {
    $query = interproceduralQuery([Calls_QueryReuseTest::class, 'handle']);
    $calls = $query->calls()->get(depth: 1);

    expect($calls->map(fn (CallSite $call) => ($call->node()->name ?? null)?->toString())->all())
        ->toBe(['helper', 'helper', null, 'trim', null, 'trim'])
        ->and($calls[0]->arguments()->get('label', 0)->toString())->toBe('string(first)')
        ->and($calls[1]->arguments()->get('label', 0)->toString())->toBe('string(second)')
        ->and($calls[2]->scope())->toBe($calls[4]->scope())
        ->and($calls[2]->scope())->not->toBe($calls[0]->scope())
        ->and($calls[3]->arguments()->allUnresolved()[0])
        ->toBe($calls[3]->scope()->functionDefinition()->type->arguments['label']);

    $class = $query->definition->getScope()->index->getClass(Calls_QueryReuseTest::class);
    $definition = $class->getMethodDefinition('helper');
    $query->calls()->get(depth: 1);

    expect($class->getMethodDefinition('helper'))->toBe($definition)
        ->and($definition->getScope())->toBe($calls[2]->scope());
});

test('repeated helper instances remain independent during predicates', function () {
    $instances = interproceduralQuery([Calls_QueryReuseTest::class, 'handle'])
        ->instancesOf(Target_QueryReuseTest::class)
        ->where(function (TrackedInstance $instance) {
            expect($instance->type()->propertyTypes)->toBe([]);
            $instance->type()->propertyTypes['visited'] = new LiteralStringType('yes');

            return true;
        })
        ->get(depth: 1);

    expect($instances)->toHaveCount(2)
        ->and($instances[0]->type())->not->toBe($instances[1]->type());
});

test('reusing a query observes its current scopes and requested depth', function () {
    $query = interproceduralQuery([Calls_QueryReuseTest::class, 'handle'])->calls();
    $calls = $query->get(depth: 1);

    expect($query->get(depth: 0))->toHaveCount(2);

    $calls[2]->scope()->calls = [];

    expect($query->get(depth: 1))->toHaveCount(2);
});

class Calls_QueryReuseTest
{
    public function handle(): void
    {
        $this->helper('first');
        $this->helper(label: 'second');
    }

    public function helper(string $label): void
    {
        new Target_QueryReuseTest;
        trim($label);
    }
}

class Target_QueryReuseTest {}
