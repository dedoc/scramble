<?php

namespace Dedoc\Scramble\Support\Infer\Scope;

use Dedoc\Scramble\Support\Type\PendingReturnType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;

class PendingTypes
{
    /** @var array{0: Type, 1: callable(PendingReturnType, Type): void, 2: int} */
    private array $references = [];

    public function addReference(Type $type, callable $referenceResolver, int $pendingTypesCount)
    {
        $this->references[] = [$type, $referenceResolver, $pendingTypesCount];
    }

    public function resolve()
    {
        $hasResolvedSomeReferences = false;

        foreach ($this->references as $index => [$type, $referenceResolver]) {
            /** @var PendingReturnType[] $pendingTypes */
            $pendingTypes = (new TypeWalker)->find($type, fn ($t) => $t instanceof PendingReturnType);

            foreach ($pendingTypes as $pendingType) {
                $resolvedType = $pendingType->scope->getType($pendingType->node);

                if ($resolvedType instanceof PendingReturnType) {
                    continue;
                }

                if ($resolvedType instanceof UnknownType) {
                    continue;
                }

                if (count((new TypeWalker)->find($resolvedType, fn ($t) => $t === $pendingType))) {
                    $resolvedType = new UnknownType;
                }

                $referenceResolver($pendingType, $resolvedType);
                $this->references[$index][2]--;
                $hasResolvedSomeReferences = true;
            }
        }

        foreach ($this->references as $index => [,,$count]) {
            if ($count === 0) {
                unset($this->references[$index]);
            }
        }

        // Something was resolved, so this can allow resolving other pending return types.
        // Performance bottleneck may be here, so some smarter way of dependencies resolving
        // may be used to avoid recursion.
        if ($hasResolvedSomeReferences) {
            $this->resolve();
        }
    }

    public function resolveAllPendingIntoUnknowns()
    {
        foreach ($this->references as [$type, $referenceResolver]) {
            /** @var PendingReturnType[] $pendingTypes */
            $pendingTypes = (new TypeWalker)->find($type, fn ($t) => $t instanceof PendingReturnType);

            foreach ($pendingTypes as $pendingType) {
                $resolvedType = $pendingType->defaultType;

                $referenceResolver($pendingType, $resolvedType);
            }
        }

        $this->references = [];
    }
}
