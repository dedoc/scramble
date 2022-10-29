<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node\Expr;

class PossibleExceptionInfer implements ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type
    {
        // $this->validate
        // $request->validate
        // Validator::make(...)->validate()?

        // $this->authorize
        // $this->authorizeResource in __constructor, not hehe

        // `can` middleware
        //

    }
}
