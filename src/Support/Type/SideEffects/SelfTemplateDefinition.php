<?php

namespace Dedoc\Scramble\Support\Type\SideEffects;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Services\TemplateTypesSolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class SelfTemplateDefinition
{
    public function __construct(
        public string $definedTemplate,
        public Type $type,
    ) {}

    public function apply(Generic $type, MethodCallEvent $event)
    {
        $templateDefinitions = $this->getAllDefinedTemplates($type, $event);

        $templateType = $this->type instanceof TemplateType
            ? collect($templateDefinitions)->get($this->type->name, new UnknownType)
            : $this->type;

        (new TemplateTypesSolver)->defineTemplateTypes(
            $event->getDefinition(),
            $type,
            $this->definedTemplate,
            $templateType,
        );
    }

    /**
     * Returns the map of all template names to the values. This includes template names of a class, and template names
     * of a given method.
     */
    public function getAllDefinedTemplates(Generic $type, MethodCallEvent $event): array
    {
        $classDefinition = $event->getDefinition();

        $classDefinedTemplates = (new TemplateTypesSolver)->getClassContextTemplates($type, $classDefinition);

        $methodDefinedTemplates = (new TemplateTypesSolver)->getFunctionContextTemplates($classDefinition->getMethodDefinition($event->name), $event->arguments);

        return array_merge($classDefinedTemplates, $methodDefinedTemplates);
    }
}
