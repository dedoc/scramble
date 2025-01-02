<?php

use Dedoc\Scramble\Infer\FlowNodes\FlowBuildingVisitor;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeGetter;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeResolver;
use Dedoc\Scramble\Infer\FlowNodes\LazyIndex;
use Dedoc\Scramble\Infer\Reflector\FunctionReflector;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;

function wow () {
    return nice();
}

function nice () {
    return wow();
}

it('builds flow nodes', function () {
    $funcReflector = FunctionReflector::makeFromCodeString(
        'iffy',
        <<<'EOF'
<?php

function iffy (array $a) {
  return nice();
}
EOF,
        new LazyIndex(),
    );

//    $funcReflector->getIncompleteType();
//
//    $funcReflector->getType();

    dd([
        $funcReflector->getIncompleteType()->toString() => $funcReflector->getType()->toString(),
    ]);
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
