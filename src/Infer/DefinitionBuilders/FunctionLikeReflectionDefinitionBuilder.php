<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

class FunctionLikeReflectionDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    private ReflectionFunction|ReflectionMethod $reflection;

    /** @var Collection<string, covariant Type> */
    private Collection $classTemplates;

    /**
     * @param Collection<string, covariant Type>|null $classTemplates
     */
    public function __construct(
        public string $name,
        ReflectionFunction|ReflectionMethod|null $reflection = null,
        ?Collection $classTemplates = null,
    ) {
        $this->reflection = $reflection ?: new ReflectionFunction($this->name);
        $this->classTemplates = $classTemplates ?: collect();
    }

    public function build(): FunctionLikeDefinition
    {
        $functionDefinition = $this->buildFunctionDefinitionFromTypeHints();

        $this->applyPhpDoc(
            $functionDefinition,
            PhpDoc::parse($this->reflection->getDocComment() ?: '/** */', FileNameResolver::createForFile($this->reflection->getFileName())),
        );

        $functionDefinition->isFullyAnalyzed = true;

        return $functionDefinition;
    }

    private function buildFunctionDefinitionFromTypeHints(): FunctionLikeDefinition
    {
        $parameters = collect($this->reflection->getParameters())
            ->mapWithKeys(fn (ReflectionParameter $p) => [
                $p->name => ($paramType = $p->getType())
                    ? TypeHelper::createTypeFromReflectionType($paramType)
                    : new MixedType,
            ])
            ->all();

        $argumentDefaults = collect($this->reflection->getParameters())
            ->mapWithKeys(fn (ReflectionParameter $p) => [
                $p->name => rescue(function () use ($p) {
                    return TypeHelper::createTypeFromValue($p->getDefaultValue());
                }, report: false),
            ])
            ->filter()
            ->all();

        $returnType = ($retType = $this->reflection->getReturnType())
            ? TypeHelper::createTypeFromReflectionType($retType)
            : new UnknownType;

        $type = new FunctionType($this->name, $parameters, $returnType);

        return new FunctionLikeDefinition(
            $type,
            argumentsDefaults: $argumentDefaults,
            definingClassName: $this->reflection instanceof ReflectionMethod ? $this->reflection->class : null,
        );
    }

    private function applyPhpDoc(FunctionLikeDefinition $definition, PhpDocNode $phpDoc): void
    {
        foreach ($phpDoc->getTemplateTagValues() as $templateTagValue) {
            $definition->type->templates[] = new TemplateType(
                name: $templateTagValue->name,
            );
        }

        foreach ($phpDoc->getParamTagValues() as $paramTagValue) {
            $name = Str::replaceFirst('$', '', $paramTagValue->parameterName);
            if (! array_key_exists($name, $definition->type->arguments)) {
                continue;
            }
            $definition->type->arguments[$name] = $this->handleStatic(
                PhpDocTypeHelper::toType($paramTagValue->type),
                $definition->type->templates,
            );
        }

        foreach ($phpDoc->getThrowsTagValues() as $throwsTagValue) {
            $definition->type->exceptions[] = $this->handleStatic(
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
    }

    /**
     * @param  TemplateType[]  $functionTemplates
     */
    private function handleStatic(Type $type, array $functionTemplates): Type
    {
        $functionTemplatesByKeys = collect($functionTemplates)->keyBy->name;

        return (new TypeWalker)->map($type, function (Type $t) use ($functionTemplatesByKeys) {
            if (! $t instanceof ObjectType) {
                return $t;
            }

            $t->name = ltrim($t->name, '\\');

            if ($definedTemplate = $this->classTemplates->get($t->name)) {
                return $definedTemplate;
            }

            if ($definedFnTemplate = $functionTemplatesByKeys->get($t->name)) {
                return $definedFnTemplate;
            }

            return $t;
        });
    }
}
