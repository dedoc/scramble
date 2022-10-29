<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser\Node\Expr;

/**
 * I know, this name cannot be pronounced from the first try.
 */
interface ExpressionExceptionExtension extends InferExtension
{
    /**
     * @return array<ObjectType|Generic> Possible extensions returned from an expression.
     */
    public function getException(Expr $node, Scope $scope): array;
}
