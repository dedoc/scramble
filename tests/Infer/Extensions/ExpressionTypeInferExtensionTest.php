<?php

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node\Expr;

it('uses expression type infer extension', function () {
    $extension = new class implements ExpressionTypeInferExtension
    {
        public function getType(Expr $node, Scope $scope): ?Type
        {
            if ($node instanceof Expr\MethodCall && $node->name->toString() === 'callWow') {
                return new LiteralStringType('wow');
            }

            return null;
        }
    };

    $type = analyzeClass(ExpressionTypeInferExtensionTest_Test::class, [$extension])
        ->getClassDefinition(ExpressionTypeInferExtensionTest_Test::class)
        ->getMethodDefinition('foo')
        ->type->getReturnType();

    expect($type->toString())->toBe('string(wow)');
});

class ExpressionTypeInferExtensionTest_Test
{
    public function foo()
    {
        return $this->callWow();
    }
}
