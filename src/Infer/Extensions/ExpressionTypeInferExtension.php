<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node\Expr;

interface ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type;
}
