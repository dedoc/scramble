<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node\Expr;

/**
 * Adds the information about type's expression location to the inferred type, so the data can be later used
 * to have the data when making exceptions, etc.
 */
class TypeTraceInfer implements ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type
    {
        if ($scope->isInClass()) {
            $type = $scope->getType($node);

            $type->setAttribute('file', $scope->context->classDefinition->name);
            $type->setAttribute('line', $node->getAttribute('startLine'));
        }

        return null;
    }
}
