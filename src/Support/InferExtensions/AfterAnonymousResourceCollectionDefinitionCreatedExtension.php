<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\VoidType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class AfterAnonymousResourceCollectionDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === AnonymousResourceCollection::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $tResource = collect($definition->templateTypes)->firstOrFail('name', 'TResource');
        $tCollects = collect($definition->templateTypes)->firstOrFail('name', 'TCollects');

        $definition->methods['__construct'] = new ShallowFunctionDefinition(
            type: new FunctionType(
                name: '__construct',
                arguments: [
                    'resource' => $tResource,
                    'collects' => $tCollects,
                ],
                returnType: new VoidType,
            ),
            definingClassName: JsonResource::class,
            // @todo now required, but should not be required!
            selfOutType: new Generic('self', [$tResource, new TemplatePlaceholderType, $tCollects]),
        );
    }
}
