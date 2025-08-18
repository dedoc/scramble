<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\TemplateType;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AfterResourceCollectionClassDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return is_a($name, ResourceCollection::class, true);
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event)
    {
        $definition = $event->classDefinition;

        $definition->templateTypes = $this->makeTemplateTypes($definition->templateTypes);
    }

    /**
     * @param  TemplateType[]  $templateTypes
     * @return TemplateType[]
     */
    private function makeTemplateTypes(array $templateTypes): array
    {
        return [
            // TResource
            collect($templateTypes)->last(fn (TemplateType $t) => $t->name === 'TResource', new TemplateType('TResource')),
            // TCollects
            collect($templateTypes)->last(fn (TemplateType $t) => $t->name === 'TCollects', new TemplateType('TCollects')),
        ];
    }
}
