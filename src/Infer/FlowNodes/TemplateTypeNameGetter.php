<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;

class TemplateTypeNameGetter
{
    public function __construct(
        private FlowNodes|ClassDefinition|null $templateTypesContainer = null,
        private ?TemplateTypeNameGetter $parent = null,
    )
    {
    }

    private function getDefinedTemplates(): array
    {
        $parentTemplates = $this->parent?->getDefinedTemplates() ?? [];

        $currentTemplates = $this->templateTypesContainer ? match ($this->templateTypesContainer::class) {
            FlowNodes::class => ($enterNode = $this->templateTypesContainer->nodes[0] ?? null) instanceof EnterFunctionLikeFlowNode
                ? $enterNode->getParametersTypesDeclaration()[0]
                : [],
            ClassDefinition::class => $this->templateTypesContainer->templateTypes,
        } : [];

        return array_merge($parentTemplates, $currentTemplates);
    }

    public function get(string $name): string
    {
        $parentTemplates = $this->parent?->getDefinedTemplates() ?? [];

        $duplicateTemplates = collect($parentTemplates)
            ->pluck('name')
            ->unique()
            ->values()
            ->filter(fn ($n) => preg_match('/^'.$name.'(\d*)?$/m', $n) === 1)
            ->all();

        return $name.($duplicateTemplates ? count($duplicateTemplates) : '');
    }
}
