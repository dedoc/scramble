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
