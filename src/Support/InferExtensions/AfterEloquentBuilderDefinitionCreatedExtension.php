<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\TemplateType;
use Illuminate\Database\Eloquent\Builder;

class AfterEloquentBuilderDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    use BuildsEagerLoadMethodDefinitions;

    public function shouldHandle(string $name): bool
    {
        return $name === Builder::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $tModel = collect($event->classDefinition->templateTypes)->firstWhere('name', 'TModel');
        if (! $tModel) {
            $event->classDefinition->templateTypes = array_merge(
                $event->classDefinition->templateTypes,
                [$tModel = new TemplateType('TModel')],
            );
        }

        $event->classDefinition->methods['with'] = $this->buildWithMethodDefinition(Builder::class, $tModel);
        $event->classDefinition->methods['without'] = $this->buildWithoutMethodDefinition(Builder::class, $tModel);
        $event->classDefinition->methods['withOnly'] = $this->buildWithOnlyMethodDefinition(Builder::class, $tModel);
        $event->classDefinition->methods['setEagerLoads'] = $this->buildSetEagerLoadsMethodDefinition(Builder::class, $tModel);
        $event->classDefinition->methods['withoutEagerLoads'] = $this->buildWithoutEagerLoadsMethodDefinition(Builder::class, $tModel);
    }
}
