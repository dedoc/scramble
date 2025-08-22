<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;

trait MergesOpenApiObjects
{
    protected function mergeOpenApiObjects(OpenApiTypes\ObjectType $into, OpenApiTypes\Type $what): void
    {
        if (! $what instanceof OpenApiTypes\ObjectType) {
            return;
        }

        foreach ($what->properties as $name => $property) {
            if (! array_key_exists($name, $into->properties)) {
                $into->addProperty($name, $property);
            } else {
                if (
                    $into->properties[$name] instanceof OpenApiTypes\ObjectType
                    && $property instanceof OpenApiTypes\ObjectType
                ) {
                    $this->mergeOpenApiObjects($into->properties[$name], $property);
                }
            }
        }

        $into->addRequired($what->required);
    }
}
