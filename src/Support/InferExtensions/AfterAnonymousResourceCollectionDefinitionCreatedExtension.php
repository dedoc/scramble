<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\VoidType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class AfterAnonymousResourceCollectionDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function __construct(private Index $index) {}

    public function shouldHandle(string $name): bool
    {
        return $name === AnonymousResourceCollection::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        if ($definition->parentFqn) {
            $definition->templateTypes = $this->index->getClass($definition->parentFqn)->templateTypes ?? [];
        }

        $tResource = collect($definition->templateTypes)->firstOrFail('name', 'TResource');
        $tCollects = collect($definition->templateTypes)->firstOrFail('name', 'TCollects');

        $definition->methods['__construct'] = new ShallowFunctionDefinition(
            type: new FunctionType(
                name: '__construct',
                arguments: [
                    'resource' => $tResource,
                    'collects' => new GenericClassStringType($tCollects),
                ],
                returnType: new VoidType,
            ),
            definingClassName: JsonResource::class,
            // @todo now required, but should not be required!
            selfOutType: new Generic('self', [$tResource, new TemplatePlaceholderType, $tCollects]),
        );
    }
}
