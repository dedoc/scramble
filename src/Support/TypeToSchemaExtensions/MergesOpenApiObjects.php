<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;

trait MergesOpenApiObjects
{
    protected function mergeOpenApiObjects(OpenApiTypes\ObjectType $into, OpenApiTypes\Type $what)
    {
        if (! $what instanceof OpenApiTypes\ObjectType) {
            return;
        }

        foreach ($what->properties as $name => $property) {
            $into->addProperty($name, $property);
        }

        $into->addRequired(array_keys($what->properties));
    }
}
