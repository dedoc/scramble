<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;

class ReferenceTypeResolver
{
    public function __construct(
        private Index $index,
    ) {
    }

    public static function hasResolvableReferences(Type $type): bool
    {
        return (bool) (new TypeWalker)->firstPublic(
            $type,
            fn (Type $t) => $t instanceof AbstractReferenceType,
        );
    }

    public function resolve(Type $type, bool $resolveNested = false): Type
    {
        return (new TypeWalker)->replacePublic(
            $type,
            function (Type $t) use ($type, $resolveNested) {
                $resolver = function () use ($t) {
                    if ($t instanceof MethodCallReferenceType) {
                        return $this->resolveMethodCallReferenceType($t);
                    }

                    if ($t instanceof CallableCallReferenceType) {
                        return $this->resolveCallableCallReferenceType($t);
                    }

                    return null;
                };

                if (! $resolved = $resolver()) {
                    return null;
                }

                if ($resolved === $type) {
                    return new UnknownType('self reference');
                }

                if ($resolved instanceof AbstractReferenceType) {
                    return $resolveNested ? $resolved : new UnknownType();
                }

                return $resolved;
            },
        );
    }

    private function resolveMethodCallReferenceType(MethodCallReferenceType $type)
    {
        $calleeType = $this->resolve($type->callee, resolveNested: true);

        if ($calleeType instanceof AbstractReferenceType) {
            // Callee cannot be resolved.
            return $type;
        }

        // @todo: pass arguments
        return $calleeType->getMethodCallType($type->methodName);
    }

    private function resolveCallableCallReferenceType(CallableCallReferenceType $type)
    {
        $calleeType = $this->index->getFunctionType($type->callee);

        if (! $calleeType) {
            // Callee cannot be resolved from index.
            return $type;
        }

        // @todo: callee now can be either in index or not, add support for other cases.
        // if ($calleeType instanceof AbstractReferenceType) {
        //    // Callee cannot be resolved.
        //    return $type;
        //}

        // @todo: pass arguments
        return $calleeType->getReturnType();
    }
}
