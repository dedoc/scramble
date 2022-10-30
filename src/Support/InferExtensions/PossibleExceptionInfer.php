<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;

class PossibleExceptionInfer implements ExpressionExceptionExtension
{
    public function getException(Expr $node, Scope $scope): array
    {
        // $this->validate
        // $request->validate
        if ($node instanceof Expr\MethodCall) {
            $isCallToValidate = $node->name instanceof Identifier && $node->name->name === 'validate';
            if (
                $isCallToValidate
                && (
                    $scope->getType($node->var)->isInstanceOf(Request::class) // $request
                    || ($node->var instanceof Expr\Variable && ($node->var->name ?? null) === 'this') // $this
                )
            ) {
                return [
                    new ObjectType(ValidationException::class),
                ];
            }
        }
        // Validator::make(...)->validate()?

        // $this->authorize
        // $this->authorizeResource in __constructor, not hehe

        // `can` middleware
        // TODO: Implement getException() method.
        return [];
    }
}
