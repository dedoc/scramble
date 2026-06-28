<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;

trait WithCollectedPaginatedItems
{
    private function toNormalizedPaginatorType(string $paginatorClass, Generic $type): ?Generic
    {
        if (! $collectedType = $this->getCollectedType($type)) {
            return null;
        }

        return new Generic($paginatorClass, [new IntegerType, $collectedType]);
    }

    private function getCollectedType(Generic $type): ?ObjectType
    {
        // When the paginated type is inferred, the collected type is second template argument. When
        // it is manually constructed, it is the single template argument.
        $collectedType = $type->templateTypes[1] ?? $type->templateTypes[0] ?? null;

        if (! $collectedType instanceof ObjectType) {
            return null;
        }

        return $collectedType;
    }
}
