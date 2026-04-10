<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\StaticReference;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\UnknownType;
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

        $definition->templateTypes = [
            ...$definition->templateTypes,
            new TemplateType('TJsonApiLinks', is: new ArrayType, default: new KeyedArrayType),
            new TemplateType('TJsonApiMeta', is: new ArrayType, default: new KeyedArrayType),
            new TemplateType('TUsesRequestQueryString', is: new BooleanType, default: new LiteralBooleanType(true)),
            new TemplateType('TIncludesPreviouslyLoadedRelationships', is: new BooleanType, default: new LiteralBooleanType(false)),
            new TemplateType('TLoadedRelationshipsMap', is: new UnknownType),
            new TemplateType('TLoadedRelationshipIdentifiers', is: new ArrayType, default: new KeyedArrayType),
        ];

        $definition->methods['newCollection'] = $this->buildNewCollectionMethodDefinition();
        $definition->methods['respectFieldsAndIncludesInQueryString'] = $this->buildRespectFieldsAndIncludesInQueryStringMethodDefinition();
        $definition->methods['ignoreFieldsAndIncludesInQueryString'] = $this->buildIgnoreFieldsAndIncludesInQueryStringMethodDefinition();
        $definition->methods['includePreviouslyLoadedRelationships'] = $this->buildIncludePreviouslyLoadedRelationshipsMethodDefinition();
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

    private function buildRespectFieldsAndIncludesInQueryStringMethodDefinition(): ShallowFunctionDefinition
    {
        $templates = [
            $tValue1 = new TemplateType('TValue1', is: new BooleanType),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'respectFieldsAndIncludesInQueryString',
                arguments: [
                    'value' => $tValue1,
                ],
                returnType: new SelfType(JsonApiResource::class),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            argumentsDefaults: [
                'value' => new LiteralBooleanType(true),
            ],
            definingClassName: JsonApiResource::class,
            selfOutType: new Generic('self', [
                new TemplatePlaceholderType, // TResource
                new TemplatePlaceholderType, // TAdditional
                new TemplatePlaceholderType, // TJsonApiLinks
                new TemplatePlaceholderType, // TJsonApiMeta
                $tValue1, // TUsesRequestQueryString
                new TemplatePlaceholderType, // TIncludesPreviouslyLoadedRelationships
                new TemplatePlaceholderType, // TLoadedRelationshipsMap
                new TemplatePlaceholderType, // TLoadedRelationshipIdentifiers
            ]),
        );
    }

    private function buildIgnoreFieldsAndIncludesInQueryStringMethodDefinition(): ShallowFunctionDefinition
    {
        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'ignoreFieldsAndIncludesInQueryString',
                returnType: new SelfType(JsonApiResource::class),
            ),
            definingClassName: JsonApiResource::class,
            selfOutType: new Generic('self', [
                new TemplatePlaceholderType, // TResource
                new TemplatePlaceholderType, // TAdditional
                new TemplatePlaceholderType, // TJsonApiLinks
                new TemplatePlaceholderType, // TJsonApiMeta
                new LiteralBooleanType(false), // TUsesRequestQueryString
                new TemplatePlaceholderType, // TIncludesPreviouslyLoadedRelationships
                new TemplatePlaceholderType, // TLoadedRelationshipsMap
                new TemplatePlaceholderType, // TLoadedRelationshipIdentifiers
            ]),
        );
    }

    private function buildIncludePreviouslyLoadedRelationshipsMethodDefinition(): ShallowFunctionDefinition
    {
        return new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'includePreviouslyLoadedRelationships',
                returnType: new SelfType(JsonApiResource::class),
            ),
            definingClassName: JsonApiResource::class,
            selfOutType: new Generic('self', [
                new TemplatePlaceholderType, // TResource
                new TemplatePlaceholderType, // TAdditional
                new TemplatePlaceholderType, // TJsonApiLinks
                new TemplatePlaceholderType, // TJsonApiMeta
                new TemplatePlaceholderType, // TUsesRequestQueryString
                new LiteralBooleanType(true), // TIncludesPreviouslyLoadedRelationships
                new TemplatePlaceholderType, // TLoadedRelationshipsMap
                new TemplatePlaceholderType, // TLoadedRelationshipIdentifiers
            ]),
        );
    }
}
