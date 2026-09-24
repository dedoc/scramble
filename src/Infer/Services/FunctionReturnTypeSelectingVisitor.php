<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Support\Type\AbstractTypeVisitor;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class FunctionReturnTypeSelectingVisitor extends AbstractTypeVisitor
{
    public function leave(Type $type): ?Type
    {
        if (! $type instanceof FunctionType) {
            return null;
        }

        $inferred = $type->getReturnType();
        $declared = $type->declaredReturnType;

        if (
            $inferred->getAttribute('fromScrambleReturn') === true
            || ! $declared
            || $declared instanceof UnknownType
            || $inferred instanceof TemplateType
            || $declared->accepts($inferred)
            || $inferred->acceptedBy($declared)
        ) {
            return null;
        }

        $type->setReturnType($declared);

        return null;
    }
}
