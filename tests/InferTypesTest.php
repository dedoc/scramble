<?php

use Dedoc\Scramble\Support\ClassAstHelper;
use Dedoc\Scramble\Support\ComplexTypeHandler\ComplexTypeHandlers;
use Dedoc\Scramble\Support\Infer\TypeInferringVisitor;
use Dedoc\Scramble\Support\Type\Identifier;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use function Spatie\Snapshots\assertMatchesSnapshot;

it('gets types', function () {
    $class = new ClassAstHelper(InferTypesTest_SampleClass::class);
    $method = $class->findFirstNode(fn ($node) => $node instanceof ClassMethod);
    dd($method->getAttribute('type')->getReturnType());
})->skip();

class InferTypesTest_SampleClass
{
    public function wow($request)
    {
        return (new BrandEdge($request));
    }
}
