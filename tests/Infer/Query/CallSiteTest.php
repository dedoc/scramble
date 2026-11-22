<?php

namespace Dedoc\Scramble\Tests\Infer\Query;

use Dedoc\Scramble\Infer\Query\CallSite;
use Illuminate\Http\Request;
use PhpParser\Node\Stmt\Expression;

test('exposes statement phpdoc on direct and assigned calls', function () {
    $calls = interproceduralQuery([Calls_CallSiteTest::class, 'handle'])
        ->calls()
        ->on(Request::class)
        ->get(depth: 0);

    expect($calls->map(fn (CallSite $call) => $call->phpDoc()?->getAttribute('summary'))->all())
        ->toBe(['How many things are there.', 'Weight of the item.', null])
        ->and($calls[0]->phpDoc()->getTagsByName('@query'))->not->toBeEmpty()
        ->and($calls[1]->phpDoc()->getTagsByName('@default'))->not->toBeEmpty()
        ->and($calls[2]->phpDoc())->toBeNull();
});

test('exposes plain statement comments on direct and assigned calls', function () {
    $calls = interproceduralQuery([Calls_CallSiteTest::class, 'plainComments'])
        ->calls()
        ->on(Request::class)
        ->get(depth: 0);

    expect($calls->map(fn (CallSite $call) => $call->commentNode()->getComments()[0]->getText())->all())
        ->toBe(['// How many things are there.', '// Weight of the item.']);

    foreach ($calls as $call) {
        expect($call->commentNode())->toBeInstanceOf(Expression::class)
            ->and($call->phpDoc())->toBeNull();
    }
});

class Calls_CallSiteTest
{
    public function plainComments(Request $request): void
    {
        // How many things are there.
        $param = $request->integer('count', 10);

        // Weight of the item.
        $request->float('weight');
    }

    public function handle(Request $request): void
    {
        /**
         * How many things are there.
         *
         * @query
         */
        $request->integer('count', 10);

        /**
         * Weight of the item.
         *
         * @default 0.5
         */
        $weight = $request->float('weight');

        $request->boolean('is_foo');
    }
}
