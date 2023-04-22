<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;

class ReferenceTypeResolver
{
    public function __construct(
        private Index $index,
    )
    {
    }

    public function resolve(Type $type): Type
    {
        if ($type instanceof MethodCallReferenceType) {
            return $this->resolveMethodCallReferenceType($type);
        }

        return $type;
    }

    private function resolveMethodCallReferenceType(MethodCallReferenceType $type)
    {
        $calleeType = $this->resolve($type->callee);

        if ($calleeType instanceof AbstractReferenceType) {
            // Callee cannot be resolved.
            return $type;
        }

        // @todo: pass arguments
        return $calleeType->getMethodCallType($type->methodName);
    }
}
