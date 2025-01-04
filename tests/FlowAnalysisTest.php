<?php

use Dedoc\Scramble\Infer\FlowNodes\FlowBuildingVisitor;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeGetter;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeResolver;
use Dedoc\Scramble\Infer\FlowNodes\LazyIndex;
use Dedoc\Scramble\Infer\Reflector\ClassReflector_V2;
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
use Dedoc\Scramble\Tests\Utils\TestUtils;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;


class Foo {
    public $a;
    public $b;
    public function __construct($a) {
        $this->a = $a;
        $this->b = ['wow' => $a];
    }
}

$var = new Foo("a");



function array_maker ($a) {
    return ['a' => $a];
}

it('builds flow nodes', function () {
//    $function = FunctionReflector::makeFromCodeString(
//        'foo',
//        <<<'EOF'
//<?php
//function foo ($a) {
//    return fn ($a) => $a;
//}
//EOF,
//        new LazyIndex(),
//    );
//
//    expect($function->getIncompleteType()->toString())->toBe('<TA>(TA): <TA_>(TA_): TA_');
//
//    dd(
//        $function->getIncompleteType()->toString(),
//    );


    $result = TestUtils::getExpressionType(
        'new Foo("a")',
        classesDefinitions: [
            'Foo' => $definition = ClassReflector_V2::makeFromCodeString('Foo', <<<'EOF'
<?php
class Foo {
    public $a;
    public $b;
    public function __construct($a) {
        $this->a = $a;
        $this->b = ['wow' => $a];
    }
}
EOF)->getDefinition()
        ],
    );

    $resolver = new IncompleteTypeResolver($result->index);

    dd([
        $result->type->toString() => $resolver->resolve($result->type)->toString(), // Foo<int(34)>
    ], $definition);


//    $def = $classReflector->getDefinition();
//
//    dd($def);
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
