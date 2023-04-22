<?php

use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;

it('infers manually thrown exceptions', function () {
    $type = analyzeFile(<<<'EOD'
<?php
function foo () {
    throw new \Exception();
}
EOD)->getFunctionType('foo');

    expect($type)->not()->toBeNull()
        ->and($type->exceptions)->toHaveCount(1)
        ->and($type->exceptions[0]->name)->toBe(Exception::class);
});

it('infers exceptions thrown from other functions', function () {
    $type = analyzeFile(<<<'EOD'
<?php
function foo () {
    return bar();
}
function bar () {
    throw new \Exception();
}
EOD)->getFunctionType('foo');

    expect($type)->not()->toBeNull()
        ->and($type->exceptions)->toHaveCount(1)
        ->and($type->exceptions[0]->name)->toBe(Exception::class);
});

it('infers exceptions thrown from other methods', function () {
    $type = analyzeFile(<<<'EOD'
<?php
class Foo {
    public function foo () {
        return $this->bar();
    }
    public function bar () {
        throw new \Exception();
    }
}
EOD)->getClassType('Foo');

    expect($type->methods['foo'])->not()->toBeNull()
        ->and($type->methods['foo']->exceptions)->toHaveCount(1)
        ->and($type->methods['foo']->exceptions[0]->name)->toBe(Exception::class);
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
    ])->getFunctionType('foo');

    expect($type)->not()->toBeNull()
        ->and($type->exceptions)->toHaveCount(1)
        ->and($type->exceptions[0]->name)->toBe(Exception::class);
});
