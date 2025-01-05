<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\ClassAutoResolvingDefinition as ClassAutoResolvingDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeAutoResolvingDefinition as FunctionLikeAutoResolvingDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeResolver;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;

class ClassAutoResolvingDefinition implements ClassAutoResolvingDefinitionContract
{
    private ?FunctionLikeAutoResolvingDefinitionContract $resolvedConstructorDefinition = null;

    public function __construct(
        public readonly ClassDefinitionContract $definition,
        private readonly Index $index,
    ) {}

    public function getData(): ClassDefinitionData
    {
        return $this->definition->getData();
    }

    public function getMethod(string $name): ?FunctionLikeAutoResolvingDefinitionContract
    {
        if (! $this->definition->getMethod($name)) {
            return null;
        }

        $methodDefinition = $this->definition->getMethod($name);
        if ($name !== '__construct') {
            return new FunctionLikeAutoResolvingDefinition(
                $methodDefinition,
                $this->index,
            );
        }

        if ($this->resolvedConstructorDefinition) {
            return $this->resolvedConstructorDefinition;
        }

        $data = $this->getData();

        // Template defaults can contain unresolved types, hence we want to resolve them
        foreach ($data->templateTypes as $templateType) {
            if (! $templateType->default) {
                continue;
            }
            $templateType->default = (new IncompleteTypeResolver($this->index))->resolve($templateType->default);
        }

        $constructFunctionType = $methodDefinition->getType();
        $resolvedConstructFunctionType = clone $constructFunctionType;

        // We want to remove all the constructor templates if they are not occurring in template types
        // or template types defaults (after the resolution)
        $templatesReferencedInClassTemplates = $data->templateTypes;
        foreach ($data->templateTypes as $templateType) {
            if (! $templateType->default) {
                continue;
            }
            $defaultsTemplates = (new TypeWalker)->findAll(
                $templateType->default,
                fn (Type $t) => $t instanceof TemplateType,
            );
            $templatesReferencedInClassTemplates = array_merge($templatesReferencedInClassTemplates, $defaultsTemplates);
        }
        $resolvedConstructFunctionType->templates = array_values(array_filter(
            $resolvedConstructFunctionType->templates,
            fn ($t) => in_array($t, $templatesReferencedInClassTemplates),
        ));
        $resolvedConstructFunctionType->arguments = array_map(
            fn ($t) => $t instanceof TemplateType
                ? in_array($t, $templatesReferencedInClassTemplates) ? $t : ($t->is ?: new MixedType)
                : $t,
            $resolvedConstructFunctionType->arguments
        );

        $constructFunctionType->setAttribute('resolvedType', $resolvedConstructFunctionType);
        $methodDefinition->type = $constructFunctionType;

        return $this->resolvedConstructorDefinition = new FunctionLikeAutoResolvingDefinition(
            $methodDefinition,
            $this->index,
        );
    }
}
