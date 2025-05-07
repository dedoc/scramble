<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node;
use PhpParser\Node\Expr;

class JsonResourceCreationInfer implements ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type
    {
        /*
         * new JsonResource
         */
        if (
            $node instanceof Expr\New_
            && ($node->class instanceof Node\Name && is_a($node->class->toString(), JsonResource::class, true))
        ) {
            return $this->setResourceType(new Generic($node->class->toString()), $scope, $node->args);
        }

        return null;
    }

    /**
     * @param  Node\Arg[]  $args
     */
    private function setResourceType(Generic $obj, Scope $scope, array $args)
    {
        $obj->templateTypes[0] = TypeHelper::getArgType($scope, $args, ['resource', 0]);

        return $obj;
    }
}
