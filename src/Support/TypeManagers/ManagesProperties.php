<?php

namespace Dedoc\Scramble\Support\TypeManagers;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;

trait ManagesProperties
{
    public function getPropertyType(Generic $type, string $propertyName): ?Type
    {
        $indexOfPropertyTemplateTypeInGeneric = array_search(self::ORDERED_PROPERTY_TO_TEMPLATE_MAP[$propertyName] ?? null, array_values(self::ORDERED_PROPERTY_TO_TEMPLATE_MAP));

        if ($indexOfPropertyTemplateTypeInGeneric === false) {
            return null;
        }

        return $type->templateTypes[$indexOfPropertyTemplateTypeInGeneric] ?? null;
    }

    public function setPropertiesTypes(Generic $type, array $propertiesTypes): Generic
    {
        foreach ($propertiesTypes as $propertyName => $propertyType) {
            $indexOfPropertyTemplateTypeInGeneric = array_search(self::ORDERED_PROPERTY_TO_TEMPLATE_MAP[$propertyName] ?? null, array_values(self::ORDERED_PROPERTY_TO_TEMPLATE_MAP));

            if ($indexOfPropertyTemplateTypeInGeneric === false) {
                continue;
            }

            $type->templateTypes[$indexOfPropertyTemplateTypeInGeneric] = $propertyType;
        }

        return $type;
    }
}
