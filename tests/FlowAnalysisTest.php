<?php

use Dedoc\Scramble\Infer\FlowNodes\FlowBuildingVisitor;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeGetter;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeResolver;
use Dedoc\Scramble\Infer\FlowNodes\LazyIndex;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\NodeTraverser;

it('builds flow nodes', function () {
    $parser = (new PhpParser\ParserFactory())->createForHostVersion();

    $code = <<<'EOF'
<?php

function iffy (string $a) {
  $b = fn () => 42;
  return $b();
}
EOF;

    $traverser = new NodeTraverser(
//        new PhpParser\NodeVisitor\ParentConnectingVisitor(),
//        new \PhpParser\NodeVisitor\NameResolver(),
    );
    $traverser->addVisitor($flowVisitor = new FlowBuildingVisitor($traverser));


    $traverser->traverse($ast = $parser->parse($code));

    $fooFlowNodes = $flowVisitor->symbolsFlowNodes['iffy']->nodes;

    $index = new LazyIndex();
    $incompleteTypesResolver = new IncompleteTypeResolver($index);

    $returnType = (new IncompleteTypeGetter())->getFunctionReturnType($fooFlowNodes);

    dd([$returnType->toString() => $incompleteTypesResolver->resolve($returnType)->toString()]);

    dd((new IncompleteTypeGetter())->getFunctionReturnType($fooFlowNodes)->toString());
});

//    \Illuminate\Support\Benchmark::dd(fn () => $traverser->traverse(
//        $parser->parse(file_get_contents((new ReflectionClass(\Illuminate\Database\Eloquent\Model::class))->getFileName())),
//        $parser->parse($code),
//    ));

//
//    dd($flowVisitor->symbolsFlowNodes);
//
//    $ast = $parser->parse(file_get_contents((new ReflectionClass(\Illuminate\Database\Eloquent\Model::class))->getFileName()));
//    $time = \Illuminate\Support\Benchmark::dd(function () use ($ast, $parser, $code, $traverser, $flowVisitor) {
//        $traverser->traverse($ast);
//
//        foreach ($flowVisitor->symbolsFlowNodes as $name => $symbolsFlowNodes) {
//            $res = [
//                $name => (new IncompleteTypeGetter())->getFunctionReturnType($symbolsFlowNodes->nodes)->toString(),
//            ];
//
//            dump($res);
//        }
//    }, 10);
//    $isIgnoringTouchFlowNodes = $flowVisitor->symbolsFlowNodes['isIgnoringTouch']->nodes;
//    dump((new IncompleteTypeGetter())->getFunctionReturnType($isIgnoringTouchFlowNodes)->toString());
//    dd($time);
