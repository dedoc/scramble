<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Symfony\Component\HttpFoundation\Response;

class AfterResponseDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === Response::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $definition->templateTypes = [
            new TemplateType('TContent'),
            new TemplateType('TStatus'),
            new TemplateType('THeaders'),
        ];

        $definition->methods['setContent'] = $this->buildSetContentMethodDefinition();
        $definition->methods['setStatusCode'] = $this->buildSetStatusCodeMethodDefinition();
    }

    private function buildSetContentMethodDefinition(): ShallowFunctionDefinition
    {
        $templates = [
            $tContent1 = new TemplateType('TContent1'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'setContent',
                arguments: [
                    'content' => $tContent1,
                ],
                returnType: new SelfType(Response::class),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: Response::class,
            selfOutType: new Generic('self', [
                $tContent1,
                new TemplatePlaceholderType,
                new TemplatePlaceholderType,
            ]),
        );
    }

    private function buildSetStatusCodeMethodDefinition(): ShallowFunctionDefinition
    {
        $templates = [
            $tStatus1 = new TemplateType('TStatus1'),
        ];

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: 'setStatusCode',
                arguments: [
                    'code' => $tStatus1,
                ],
                returnType: new SelfType(Response::class),
            ), function (FunctionType $ft) use ($templates) {
                $ft->templates = $templates;
            }),
            definingClassName: Response::class,
            selfOutType: new Generic('self', [
                new TemplatePlaceholderType,
                $tStatus1,
                new TemplatePlaceholderType,
            ]),
        );
    }
}
