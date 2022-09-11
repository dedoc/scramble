<?php

namespace Dedoc\Scramble\Extensions;

use Dedoc\Scramble\Support\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\Node\Expr;

interface ExpressionTypeInferExtension
{
    public function getType(Expr $node, Scope $scope): ?Type;
}
