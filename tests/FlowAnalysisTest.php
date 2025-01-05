<?php

use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeResolver;
use Dedoc\Scramble\Infer\FlowNodes\LazyIndex;
use Dedoc\Scramble\Infer\Reflection\ReflectionClass as ScrambleReflectionClass;
use Dedoc\Scramble\Tests\Utils\TestUtils;
use PhpParser\ParserFactory;

beforeEach(function () {
    $this->parser = (new ParserFactory)->createForHostVersion();
    $this->index = new LazyIndex(parser: $this->parser);
    $this->testUtils = new TestUtils($this->index, $this->parser);
});

it('builds flow nodes', function () {
    $result = $this->testUtils->getExpressionType(
        'new Foo("a")',
        classesDefinitions: [
            'Foo' => $definition = ScrambleReflectionClass::createFromSource('Foo', <<<'EOF'
<?php
class Foo {
    public $a;
    public $b;
    public function __construct($a) {
        $this->a = $a;
        $this->b = ['wow' => $a];
    }
}
EOF, $this->index, $this->parser)->getDefinition()->definition,
        ],
    );

    $resolver = new IncompleteTypeResolver($result->index);

    expect($result->type->toString())
        ->toBe('(new Foo)(string(a))')
        ->and($resolver->resolve($result->type)->toString())
        ->toBe('Foo<string(a), array{wow: string(a)}>');
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
