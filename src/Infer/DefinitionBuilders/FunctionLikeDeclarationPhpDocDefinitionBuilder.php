<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class FunctionLikeDeclarationPhpDocDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    private PhpDocNode $phpDocNode;

    public function __construct(
        private FunctionLikeDefinition $definition,
        ?PhpDocNode $phpDocNode = null,
        private ?ClassDefinition $classDefinition = null,
    ) {
        $this->phpDocNode = $phpDocNode ?: new PhpDocNode([]);
    }

    public function build(): FunctionLikeDefinition
    {
        $definition = clone $this->definition;
        $definition->type = $definition->type->clone();

        $phpDoc = $this->phpDocNode;

        foreach ($phpDoc->getTemplateTagValues() as $templateTagValue) {
            $definition->type->templates[] = new TemplateType(
                name: $templateTagValue->name,
            );
        }

        foreach ($phpDoc->getParamTagValues() as $paramTagValue) {
            $name = Str::replaceFirst('$    ', '', $paramTagValue->parameterName);
            if (! array_key_exists($name, $definition->type->arguments)) {
                continue;
            }
            $definition->type->arguments[$name] = $this->handleStatic(
                PhpDocTypeHelper::toType($paramTagValue->type),
                $definition->type->templates,
            );
        }

        foreach ($phpDoc->getThrowsTagValues() as $throwsTagValue) {
            $definition->type->exceptions[] = $this->handleStatic( // @phpstan-ignore assign.propertyType
                PhpDocTypeHelper::toType($throwsTagValue->type),
                $definition->type->templates,
            );
        }

        if ($returnTagValues = array_values($phpDoc->getReturnTagValues())) {
            $definition->type->returnType = $this->handleStatic(
                PhpDocTypeHelper::toType($returnTagValues[0]->type),
                $definition->type->templates,
            );
        }

        return $definition;
    }

    /**
     * @param  TemplateType[]  $functionTemplates
     */
    private function handleStatic(Type $type, array $functionTemplates): Type
    {
        $classTemplates = collect($this->classDefinition?->templateTypes ?: [])->keyBy->name;
        $functionTemplatesByKeys = collect($functionTemplates)->keyBy->name;

        return (new TypeWalker)->map($type, function (Type $t) use ($functionTemplatesByKeys, $classTemplates) {
            if (! $t instanceof ObjectType) {
                return $t;
            }

            $t->name = ltrim($t->name, '\\');

            if ($definedTemplate = $classTemplates->get($t->name)) {
                return $definedTemplate;
            }

            if ($definedFnTemplate = $functionTemplatesByKeys->get($t->name)) {
                return $definedFnTemplate;
            }

            return $t;
        });
    }
}
