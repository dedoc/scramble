<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\StaticReference;
use Dedoc\Scramble\Support\Type\TemplateType;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class AfterJsonApiResourceDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === JsonApiResource::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $definition->methods['newCollection'] = $this->buildNewCollectionMethodDefinition();
    }

    private function buildNewCollectionMethodDefinition(): ShallowFunctionDefinition
    {
        $templates = [
            $tResource1 = new TemplateType('TResource1'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'newCollection',
                arguments: [
                    'resource' => $tResource1,
                ],
                returnType: new Generic(AnonymousResourceCollection::class, [
                    $tResource1,
                    new ArrayType,
                    new ObjectType(StaticReference::STATIC),
                ]),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: JsonApiResource::class,
            isStatic: true,
        );
    }
}
