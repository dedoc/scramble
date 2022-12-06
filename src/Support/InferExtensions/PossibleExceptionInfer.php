<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
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
                $scope->getType($node->var)->isInstanceOf(Validator::class) // Validator::make()
                || $scope->getType($node->var)->isInstanceOf(Request::class) // $request
                || ($node->var instanceof Expr\Variable && $node->var->name === 'this')
            ) {
                if ($isCallToValidate) {
                    return [
                        new ObjectType(ValidationException::class),
                    ];
                }
            }
            // Validator::validate()?

            // $this->authorize
            if (
                $node->name instanceof Identifier && $node->name->name === 'authorize'
                && ($node->var instanceof Expr\Variable && $node->var->name === 'this') // $this
            ) {
                return [
                    new ObjectType(AuthorizationException::class),
                ];
            }
        }

        // $this->authorizeResource in __constructor
        return [];
    }
}
