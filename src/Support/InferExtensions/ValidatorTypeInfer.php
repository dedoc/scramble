<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PhpParser\Node;
use PhpParser\Node\Expr;

class ValidatorTypeInfer implements ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type
    {
        // Validator::make
        if (
            $node instanceof Node\Expr\StaticCall
            && ($node->class instanceof Node\Name && is_a($node->class->toString(), ValidatorFacade::class, true))
        ) {
            $validatorType = new ObjectType(Validator::class);

            $validatorType->properties = array_merge($validatorType->properties, [
                'rules' => TypeHelper::getArgType($scope, $node->args, ['rules', 1]),
            ]);

            return $validatorType;
        }

        return null;
    }
}
