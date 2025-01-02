<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Support\Type\CallableStringType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Reference\AbstractReferenceType;
use Dedoc\Scramble\Support\Type\Reference\CallableCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;

class IncompleteTypeResolver
{
    public function __construct(private readonly Index $index) {}

    public function resolve(Type $type)
    {
        return (new TypeWalker)->map(
            $type,
            $this->doResolve(...),
            function (Type $t) {
                $nodes = $t->nodes();
                /*
                 * When mapping function type, we don't want to affect arguments of the function types, just the return type.
                 */
                if ($t instanceof FunctionType) {
                    return array_values(array_filter($nodes, fn ($n) => $n !== 'arguments'));
                }
                return $nodes;
            },
        );
    }

    public function doResolve(Type $type): Type
    {
        if (! $type instanceof AbstractReferenceType) {
            return $type;
        }

        if ($type instanceof CallableCallReferenceType) {
            $functionType = $this->resolve(
                $type->callee instanceof CallableStringType
                    ? $this->index->getFunction($type->callee->name)
                    : $type->callee
            );

            if ($functionType instanceof FunctionType) {
                return $this->resolveFunctionLikeCall(
                    $functionType,
                    $type->arguments,
                );
            }
        }

        return new UnknownType('Cannot resolve reference type');
    }

    protected function resolveFunctionLikeCall(FunctionType $functionType, array|callable $arguments)
    {
        $returnType = $functionType->returnType;

        // if no templates - no need to resolve individual arguments!

        return $returnType;
    }
}
