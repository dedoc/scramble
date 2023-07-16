<?php

namespace Dedoc\Scramble\Infer\Extensions;

class ExtensionsBroker
{
    public function __construct(
        public readonly array $extensions,
    ) {
    }

    public function getPropertyType($event)
    {
        $extensions = array_filter($this->extensions, function ($e) use ($event) {
            return $e instanceof PropertyTypeExtension
                && $e->shouldHandle($event->getInstance());
        });

        foreach ($extensions as $extension) {
            if ($propertyType = $extension->getPropertyType($event)) {
                return $propertyType;
            }
        }

        return null;
    }

    public function getMethodReturnType($event)
    {
        $extensions = array_filter($this->extensions, function ($e) use ($event) {
            return $e instanceof MethodReturnTypeExtension
                && $e->shouldHandle($event->getInstance());
        });

        foreach ($extensions as $extension) {
            if ($propertyType = $extension->getMethodReturnType($event)) {
                return $propertyType;
            }
        }

        return null;
    }
}
