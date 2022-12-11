<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Support\Type\PendingReturnType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;

class PendingTypes
{
    /** @var array{0: Type, 1: callable(PendingReturnType, Type): void, 2: PendingReturnType[] }[] */
    private array $references = [];

    public function addReference(Type $type, callable $referenceResolver, array $pendingTypes)
    {
        $this->references[] = [$type, $referenceResolver, $pendingTypes];
    }

    public function resolve()
    {
        $hasResolvedSomeReferences = false;

        foreach ($this->references as $index => [$type, $referenceResolver, $pendingTypes]) {
            foreach ($pendingTypes as $pendingTypeIndex => $pendingType) {
                $resolvedType = $pendingType->scope->getType($pendingType->node);

                if ($resolvedType instanceof PendingReturnType) {
                    continue;
                }

                if ($resolvedType instanceof UnknownType) {
                    continue;
                }

                if (count((new TypeWalker)->find($resolvedType, fn ($t) => $t === $pendingType))) {
                    $resolvedType = $pendingType->defaultType;
                }

                $this->resolveType($pendingType, $referenceResolver, $resolvedType);

                unset($this->references[$index][2][$pendingTypeIndex]);
                $hasResolvedSomeReferences = true;
            }
        }

        foreach ($this->references as $index => [,,$pendingTypes]) {
            if (count($pendingTypes) === 0) {
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
        foreach ($this->references as [$type, $referenceResolver, $pendingTypes]) {
            foreach ($pendingTypes as $pendingType) {
                $resolvedType = $pendingType->defaultType;

                $this->resolveType($pendingType, $referenceResolver, $resolvedType);
            }
        }

        $this->references = [];
    }

    private function resolveType(PendingReturnType $pendingType, callable $referenceResolver, Type $resolvedType)
    {
        foreach ($pendingType->defaultType->attributes() as $name => $value) {
            $resolvedType->setAttribute($name, $value);
        }

        $referenceResolver($pendingType, $resolvedType);
    }
}
