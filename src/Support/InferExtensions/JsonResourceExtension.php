<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class JsonResourceExtension
{
    public function shouldHandle(ClassDefinition $type)
    {
        return $type->isInstanceOf(JsonResource::class)
            && ! $type->isInstanceOf(ResourceCollection::class);
    }

    public function getPropertyType(PropertyFetchEvent $event)
    {
        if ($event->getDefinition()->hasProperty($event->getName())) {
            return null;
        }

        $modelType = $event->getInstance()->templateTypes[0];

        if ($event->getName() === 'resource') {
            return $modelType;
        }

        return $modelType->getPropertyType($event->getName());
    }
}
