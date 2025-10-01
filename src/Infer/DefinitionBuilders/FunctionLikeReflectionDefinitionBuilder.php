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
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

class FunctionLikeReflectionDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    private ReflectionFunction|ReflectionMethod $reflection;

    /** @var Collection<string, covariant Type> */
    private Collection $classTemplates;

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
        $parameters = collect($this->reflection->getParameters())
            ->mapWithKeys(fn (ReflectionParameter $p) => [
                $p->name => ($paramType = $p->getType())
                    ? TypeHelper::createTypeFromReflectionType($paramType)
                    : new MixedType,
            ])
            ->all();

        $returnType = ($retType = $this->reflection->getReturnType())
            ? TypeHelper::createTypeFromReflectionType($retType)
            : new UnknownType;

        $type = new FunctionType($this->name, $parameters, $returnType);

        // add phpdoc annotations
        $className = $this->reflection instanceof ReflectionMethod ? $this->reflection->class : null;
        $nameResolver = FileNameResolver::createForFile($this->reflection->getFileName());

        $phpDoc = PhpDoc::parse($this->reflection->getDocComment() ?: '/** */', $nameResolver);
        foreach ($phpDoc->getThrowsTagValues() as $throwsTagValue) {
            $type->exceptions[] = $this->handleStatic(PhpDocTypeHelper::toType($throwsTagValue->type), []);
        }
        if ($returnTagValues = array_values($phpDoc->getReturnTagValues())) {
            $type->returnType = $this->handleStatic(PhpDocTypeHelper::toType($returnTagValues[0]->type), []);
        }

        return new FunctionLikeDefinition(
            $type,
            definingClassName: $className,
        );
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
