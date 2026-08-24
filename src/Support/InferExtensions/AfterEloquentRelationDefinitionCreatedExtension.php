<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Illuminate\Database\Eloquent\Relations\Relation;

class AfterEloquentRelationDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    use BuildsEagerLoadMethodDefinitions;

    public function shouldHandle(string $name): bool
    {
        return $name === Relation::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $tRelatedModel = collect($event->classDefinition->templateTypes)->firstWhere('name', 'TRelatedModel');
        if (! $tRelatedModel) {
            return;
        }

        $event->classDefinition->methods['with'] = $this->buildWithMethodDefinition(Relation::class, $tRelatedModel);
        $event->classDefinition->methods['without'] = $this->buildWithoutMethodDefinition(Relation::class, $tRelatedModel);
        $event->classDefinition->methods['withOnly'] = $this->buildWithOnlyMethodDefinition(Relation::class, $tRelatedModel);
        $event->classDefinition->methods['setEagerLoads'] = $this->buildSetEagerLoadsMethodDefinition(Relation::class, $tRelatedModel);
        $event->classDefinition->methods['withoutEagerLoads'] = $this->buildWithoutEagerLoadsMethodDefinition(Relation::class, $tRelatedModel);
    }
}
