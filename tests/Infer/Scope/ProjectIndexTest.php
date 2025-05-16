<?php

namespace Dedoc\Scramble\Tests\Infer\Scope;

use Dedoc\Scramble\Infer\Scope\LazyAstIndex;
use Dedoc\Scramble\Infer\Scope\LazyShallowReflectionIndex;
use Dedoc\Scramble\Infer\Scope\ProjectIndex;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->index = new ProjectIndex(
        reflectionIndex: new LazyShallowReflectionIndex,
        astIndex: new LazyAstIndex,
    );
});

class ProjectIndexTest_Foo
{
    public function foo() {}
}

test('analyzes local with ast', function () {
    $definition = $this->index->getClass(ProjectIndexTest_Foo::class);

    expect($definition->getData()->methods['foo']->isFullyAnalyzed)->toBeFalse();
});

test('analyzes vendor with reflection', function () {
    $definition = $this->index->getClass(Model::class);

    expect($definition->getData()->methods)->toBeEmpty();
});
