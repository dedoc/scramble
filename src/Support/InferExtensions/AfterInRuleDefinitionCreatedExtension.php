<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\VoidType;
use Illuminate\Validation\Rules\In;

class AfterInRuleDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === In::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $definition->templateTypes = [
            $tRule = new TemplateType('TRule', default: new LiteralStringType('in')),
            $tValues = new TemplateType('TValues'),
        ];

        $definition->properties['rule'] = new ClassPropertyDefinition(type: $tRule, defaultType: $tRule->default);
        $definition->properties['values'] = new ClassPropertyDefinition(type: $tValues);

        $definition->methods['__construct'] = new ShallowFunctionDefinition(
            type: new FunctionType(
                name: '__construct',
                arguments: ['values' => $tValues],
                returnType: new VoidType,
            ),
            definingClassName: In::class,
            selfOutType: new Generic('self', [
                new TemplatePlaceholderType,
                new Generic(InRuleValuesResolver::class, [$tValues, new TemplateType('Arguments')]),
            ]),
        );
    }
}
