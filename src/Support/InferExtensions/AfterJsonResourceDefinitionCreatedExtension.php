<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticReference;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\VoidType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class AfterJsonResourceDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === JsonResource::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $definition->propagatesTemplates(true);

        $definition->templateTypes = [
            $tResource = new TemplateType('TResource'),
            $tAdditional = new TemplateType('TAdditional', default: new ArrayType),
        ];

        $definition->methods['__construct'] = new ShallowFunctionDefinition(
            type: new FunctionType(
                name: '__construct',
                arguments: [
                    'resource' => $tResource,
                ],
                returnType: new VoidType,
            ),
            definingClassName: JsonResource::class,
            // @todo now required, but should not be required!
            selfOutType: new Generic('self', [$tResource, new TemplatePlaceholderType]),
        );

        $additionalTemplates = [
            $tAdditional1 = new TemplateType('TAdditional1'),
        ];
        $definition->methods['additional'] = new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'additional',
                arguments: [
                    'additional' => $tAdditional1,
                ],
                returnType: new SelfType(JsonResource::class),
            ), function (FunctionType $ft) use ($additionalTemplates) {
                $ft->templates = $additionalTemplates;
            }),
            definingClassName: JsonResource::class,
            selfOutType: new Generic('self', [
                new TemplatePlaceholderType,
                $tAdditional1,
            ])
        );

        $definition->methods['newCollection'] = $this->buildNewCollectionMethodDefinition();

        $definition->methods['collection'] = $this->buildCollectionMethodDefinition();
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
            definingClassName: JsonResource::class,
            isStatic: true,
        );
    }

    private function buildCollectionMethodDefinition(): ShallowFunctionDefinition
    {
        $templates = [
            $tResource1 = new TemplateType('TResource1'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'collection',
                arguments: [
                    'resource' => $tResource1,
                ],
                returnType: new StaticMethodCallReferenceType(
                    StaticReference::STATIC,
                    'newCollection',
                    [$tResource1],
                ),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: JsonResource::class,
            isStatic: true,
        );
    }
}
