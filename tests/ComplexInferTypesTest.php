<?php

use Dedoc\Scramble\Infer\TypeInferringVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

it('infers param type', function () {
    $code = <<<'EOD'
<?php
function foo (int $a = 4) {
    return $a;
}
EOD;

    ['scope' => $scope, 'ast' => $ast] = analyzeFile($code);

    expect($scope->getType($ast[0])->getReturnType()->toString())->toBe('int(4)');
});

it('infers type from assignment', function () {
    $code = <<<'EOD'
<?php
$a = 2;
$a = 5;
$a;
EOD;

    ['scope' => $scope, 'ast' => $ast] = analyzeFile($code);

    expect($scope->getType($ast[2]->expr)->toString())->toBe('int(5)');
});

it('assignment works with closure scopes', function () {
    $code = <<<'EOD'
<?php
$a = 2;
$b = fn () => $a;
$b;
EOD;

    ['scope' => $scope, 'ast' => $ast] = analyzeFile($code);

    expect($scope->getType($ast[2]->expr)->toString())->toBe('(): int(2)');
});

it('assignment works with fn scope', function () {
    $code = <<<'EOD'
<?php
$a = 2;
$b = function () use ($a) {
    return $a;
};
$b;
EOD;

    ['scope' => $scope, 'ast' => $ast] = analyzeFile($code);

    expect($scope->getType($ast[2]->expr)->toString())->toBe('(): int(2)');
});

it('array type is analyzed with details', function () {
    $code = <<<'EOD'
<?php
class Foo {
    public function toArray(): array
    {
        return ['foo' => 'bar'];
    }
}
EOD;

    ['scope' => $scope, 'ast' => $ast] = analyzeFile($code);

    expect($scope->getType($ast[0])->getMethodCallType('toArray')->toString())
        ->toBe('array{foo: string(bar)}');
});

/*
 * When int, float, bool, return type annotated, there is no point in using types from return
 * as there is no more useful information about the function can be extracted.
 * Sure we could've extracted some literals, but for now there is no point (?).
 */
it('uses function return annotation type when int, float, bool, used', function () {
    $code = <<<'EOD'
<?php
class Foo {
    public function bar(): int
    {
        return [];
    }
}
EOD;

    ['scope' => $scope, 'ast' => $ast] = analyzeFile($code);

    expect($scope->getType($ast[0])->getMethodCallType('bar')->toString())
        ->toBe('int');
});

/**
 * @return array{ast: PhpParser\Node\Stmt[], scope: \Dedoc\Scramble\Infer\Scope\Scope}
 */
function analyzeFile(string $code): array
{
    $fileAst = (new ParserFactory)->create(ParserFactory::PREFER_PHP7)->parse($code);

    $infer = app()->make(TypeInferringVisitor::class, ['namesResolver' => fn ($s) => $s]);
    $traverser = new NodeTraverser;
    $traverser->addVisitor($infer);
    $traverser->traverse($fileAst);

    return [
        'scope' => $infer->scope,
        'ast' => $fileAst,
    ];
}
