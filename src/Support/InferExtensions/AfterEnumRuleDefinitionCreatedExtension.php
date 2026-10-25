<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ConditionalType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\VoidType;
use Illuminate\Validation\Rules\Enum;
use UnitEnum;

class AfterEnumRuleDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === Enum::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $definition->templateTypes = [
            $tType = new TemplateType('TType'),
            new TemplateType('TValidator', default: new NullType),
            new TemplateType('TOnly', default: new KeyedArrayType),
            new TemplateType('TExcept', default: new KeyedArrayType),
        ];

        foreach (['type', 'validator', 'only', 'except'] as $index => $property) {
            $template = $definition->templateTypes[$index];
            $definition->properties[$property] = new ClassPropertyDefinition(
                type: $template,
                defaultType: $template->default,
            );
        }

        $definition->methods['__construct'] = new ShallowFunctionDefinition(
            type: new FunctionType(
                name: '__construct',
                arguments: ['type' => $tType],
                returnType: new VoidType,
            ),
            definingClassName: Enum::class,
            selfOutType: new Generic('self', [
                $tType,
                new TemplatePlaceholderType,
                new TemplatePlaceholderType,
                new TemplatePlaceholderType,
            ]),
        );

        $definition->methods['setValidator'] = $this->buildSetter('setValidator', 'validator', 1);
        $definition->methods['only'] = $this->buildSetter('only', 'values', 2);
        $definition->methods['except'] = $this->buildSetter('except', 'values', 3);
    }

    private function buildSetter(string $method, string $argument, int $templateIndex): ShallowFunctionDefinition
    {
        $value = new TemplateType('TValue');
        $assigned = $argument === 'values'
            ? new ConditionalType(
                $value,
                new ObjectType(UnitEnum::class),
                new KeyedArrayType([new ArrayItemType_(null, $value)]),
                $value,
            )
            : $value;

        $templates = [
            new TemplatePlaceholderType,
            new TemplatePlaceholderType,
            new TemplatePlaceholderType,
            new TemplatePlaceholderType,
        ];
        $templates[$templateIndex] = $assigned;

        return new ShallowFunctionDefinition(
            type: tap(new FunctionType(
                name: $method,
                arguments: [$argument => $value],
                returnType: new SelfType(Enum::class),
            ), function (FunctionType $type) use ($value) {
                $type->templates = [$value];
            }),
            definingClassName: Enum::class,
            selfOutType: new Generic('self', $templates),
        );
    }
}
