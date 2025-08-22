<?php

namespace Dedoc\Scramble\Support\TypeManagers;

use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;

/**
 * @see ResourceCollection
 */
class ResourceCollectionTypeManager
{
    public function __construct(private Generic $type, private Index $index)
    {
    }

    public function getCollectedType(): Generic|UnknownType
    {
        if ($inferredCollectedType = $this->getInferredType()) {
            return $inferredCollectedType;
        }

        if ($collectedTypeFromProperty = $this->getCollectedTypeFromPropertyDefinition()) {
            return $collectedTypeFromProperty;
        }

        return new UnknownType;
    }

    private function getInferredType(): ?Generic
    {
        // @todo implement getting the inferred type from first template type param

        $collectsClassNameType = $this->type->templateTypes[/* TCollects */ 2] ?? null;

        if (! $collectsClassNameType instanceof LiteralStringType) {
            return null;
        }

        return new Generic($collectsClassNameType->value, [new UnknownType]);
    }

    private function getCollectedTypeFromPropertyDefinition(): ?Generic
    {
        $classDefinition = $this->index->getClass($this->type->name);

        $collectingClassDefinition = $classDefinition->getPropertyDefinition('collects');

        $collectingClassType = $collectingClassDefinition?->defaultType;

        if (! $collectingClassType instanceof LiteralStringType) {
            if (
                str_ends_with($classDefinition->name, 'Collection') &&
                (class_exists($class = Str::replaceLast('Collection', '', $classDefinition->name)) ||
                    class_exists($class = Str::replaceLast('Collection', 'Resource', $classDefinition->name)))
            ) {
                return new Generic($class, [new UnknownType]);
            }
            return null;
        }

        return new Generic($collectingClassType->value, [new UnknownType]);
    }
}
