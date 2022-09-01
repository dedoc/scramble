<?php

namespace Dedoc\Scramble\Support\Infer\Scope;

use Dedoc\Scramble\Support\Type\PendingReturnType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;

class PendingTypes
{
    /** @var array{0: Type, 1: callable(PendingReturnType, Type): void} */
    private array $references = [];

    public function addReference(Type $type, callable $referenceResolver)
    {
        $this->references[] = [$type, $referenceResolver];
    }

    public function resolve()
    {
        $resolvedReferences = [];

        foreach ($this->references as $index => [$type, $referenceResolver]) {
            /** @var PendingReturnType[] $pendingTypes */
            $pendingTypes = TypeWalker::find($type, fn ($t) => $t instanceof PendingReturnType);

            foreach ($pendingTypes as $pendingType) {
                $resolvedType = $pendingType->scope->getType($pendingType->node);

                if ($resolvedType instanceof PendingReturnType) {
                    continue;
                }

                if ($resolvedType instanceof UnknownType) {
                    continue;
                }

                $referenceResolver($pendingType, $resolvedType);
                $resolvedReferences[] = $index;
            }
        }

        foreach ($resolvedReferences as $index) {
            if (isset($this->references[$index])) {
                unset($this->references[$index]);
            }
        }

        // Something was resolved, so this can allow resolving other pending return types.
        // Performance bottleneck may be here, so some smarter way of dependencies resolving
        // may be used to avoid recursion.
        if (count($resolvedReferences)) {
            $this->resolve();
        }
    }
}
