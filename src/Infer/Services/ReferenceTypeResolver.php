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

    public function resolve(Type $type): Type
    {
        return (new TypeWalker)->replacePublic(
            $type,
            function (Type $t) use ($type) {
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

                if ($resolved instanceof AbstractReferenceType) {
                    return $resolved->mergeAttributes($t->attributes());
                }

                if ($resolved === $type) {
                    return new UnknownType('self reference');
                }

                return $resolved->mergeAttributes($t->attributes());
            },
        );
    }

    private function resolveMethodCallReferenceType(MethodCallReferenceType $type)
    {
        $calleeType = $this->resolve($type->callee);

        if ($calleeType instanceof AbstractReferenceType) {
            // Callee cannot be resolved.
            return $type;
        }

        // @todo: pass arguments
        $result = $calleeType->getMethodCallType($type->methodName);

        $result->setAttribute('exceptions', $calleeType->methods[$type->methodName]->exceptions ?? []);

        return $result;
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
        $result = $calleeType->getReturnType();

        $result->setAttribute('exceptions', $calleeType->exceptions);

        return $result;
    }
}
