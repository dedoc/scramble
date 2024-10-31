<?php

use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;

/*
 * Note: type inference system does not infer other method's/functions calls exceptions.
 */

it('infers manually thrown exceptions', function () {
    $type = analyzeFile(<<<'EOD'
<?php
function foo () {
    throw new \Exception();
}
EOD)->getFunctionDefinition('foo');

    expect($type)->not()->toBeNull()
        ->and($type->type->exceptions)->toHaveCount(1)
        ->and($type->type->exceptions[0]->name)->toBe(Exception::class);
});

it('infers exceptions using expression exception extensions', function () {
    $type = analyzeFile(<<<'EOD'
<?php
function foo () {
    return bar();
}
EOD, [
        new class implements ExpressionExceptionExtension
        {
            public function getException(Expr $node, Scope $scope): array
            {
                if ($node instanceof FuncCall && $node->name->toString() === 'bar') {
                    return [new ObjectType(Exception::class)];
                }
            }
        },
    ])->getFunctionDefinition('foo');

    expect($type)->not()->toBeNull()
        ->and($type->type->exceptions)->toHaveCount(1)
        ->and($type->type->exceptions[0]->name)->toBe(Exception::class);
});
