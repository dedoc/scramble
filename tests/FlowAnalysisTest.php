<?php

use Dedoc\Scramble\Infer\FlowNodes\FlowBuildingVisitor;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeGetter;
use PhpParser\NodeTraverser;

it('builds flow nodes', function () {
    $parser = (new PhpParser\ParserFactory())->createForHostVersion();

    $code = <<<'EOF'
<?php

function iffy (string $a) {
  return (function () use ($a) {
    return $a;
  })();
}
EOF;

    $traverser = new NodeTraverser(
//        new PhpParser\NodeVisitor\ParentConnectingVisitor(),
//        new \PhpParser\NodeVisitor\NameResolver(),
    );
    $traverser->addVisitor($flowVisitor = new FlowBuildingVisitor($traverser));

//    \Illuminate\Support\Benchmark::dd(fn () => $traverser->traverse(
//        $parser->parse(file_get_contents((new ReflectionClass(\Illuminate\Database\Eloquent\Model::class))->getFileName())),
//        $parser->parse($code),
//    ));
    $traverser->traverse(
//        $parser->parse(file_get_contents((new ReflectionClass(\Illuminate\Database\Eloquent\Model::class))->getFileName())),
        $ast = $parser->parse($code),
    );
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
    $fooFlowNodes = $flowVisitor->symbolsFlowNodes['iffy']->nodes;

    dd((new IncompleteTypeGetter())->getFunctionReturnType($fooFlowNodes)->toString());
});
