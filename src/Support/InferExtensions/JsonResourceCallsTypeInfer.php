<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node;
use PhpParser\Node\Expr;

class JsonResourceCallsTypeInfer implements ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type
    {
        if (! $node instanceof Node\Expr\MethodCall || ! $node->name instanceof Node\Identifier) {
            return null;
        }

        if (! $scope->getType($node->var)->isInstanceOf(JsonResource::class)) {
            return null;
        }

        if ($node->name->toString() === 'additional' && isset($node->args[0])) {
            $type = $scope->getType($node->var);

            $objectType = $type instanceof ObjectType
                ? $type
                : $type->type ?? null; // the case then type is Generic. This is the documented case of resources.

            if (! $objectType instanceof ObjectType) {
                return null;
            }

            $objectType->properties = array_merge($objectType->properties, [
                'additional' => TypeHelper::getArgType($scope, $node->args, ['data', 0]),
            ]);

            return $type;
        }

        return null;
    }
}
