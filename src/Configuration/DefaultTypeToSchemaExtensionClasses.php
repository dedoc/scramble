<?php

declare(strict_types=1);

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\AnonymousResourceCollectionTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EloquentCollectionToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\EnumToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\LengthAwarePaginatorTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ModelToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResponseTypeToSchema;

class DefaultTypeToSchemaExtensionClasses
{
    /**
     * @return array<class-string<TypeToSchemaExtension>>
     */
    public function get(): array
    {
        return [
            EnumToSchema::class,
            JsonResourceTypeToSchema::class,
            ModelToSchema::class,
            EloquentCollectionToSchema::class,
            AnonymousResourceCollectionTypeToSchema::class,
            LengthAwarePaginatorTypeToSchema::class,
            ResponseTypeToSchema::class,
        ];
    }
}
