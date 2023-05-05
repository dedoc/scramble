<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Analyzer\MethodAnalyzer;
use Dedoc\Scramble\Infer\ProjectAnalyzer;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\NameContext;

class ClassDefinition
{
    private \ReflectionClass $reflection; // @todo: not serialize

    private ?NameContext $nameContext = null; // @todo: not serialize

    public function __construct(
        // FQ name
        public string $name,
        /** @var TemplateType[] $templateTypes */
        public array $templateTypes = [],
        /** @var array<string, ClassPropertyDefinition> $properties */
        public array $properties = [],
        /** @var array<string, FunctionLikeDefinition> $methods */
        public array $methods = [],
        public ?string $parentFqn = null,
    ) {
    }

    public function getReflection(): \ReflectionClass
    {
        if (! isset($this->reflection)) {
            $this->reflection = new \ReflectionClass($this->name);
        }
        return $this->reflection;
    }

    public function isInstanceOf(string $className)
    {
        return is_a($this->name, $className, true);
    }

    public function isChildOf(string $className)
    {
        return $this->isInstanceOf($className) && $this->name !== $className;
    }

    public function getMethodDefinition(string $name)
    {
        if (! array_key_exists($name, $this->methods)) {
            return null;
        }

        $methodDefinition = $this->methods[$name];

        if (! $methodDefinition->isFullyAnalyzed()) {
            (new MethodAnalyzer(
                app(ProjectAnalyzer::class),
                $this
            ))->analyze($methodDefinition);
        }

        return $this->methods[$name];
    }

    public function getPropertyFetchType($name, ObjectType $calledOn = null)
    {
        $propertyDefinition = $this->properties[$name] ?? null;

        if (! $propertyDefinition) {
            return new UnknownType("Cannot get property [$name] type on [$this->name]");
        }

        $type = $propertyDefinition->type;

        if (! $calledOn instanceof Generic) {
            return $propertyDefinition->defaultType ?: $type;
        }

        return $this->replaceTemplateInType($type, $calledOn->templateTypesMap);
    }

    public function getMethodCallType(string $name, ObjectType $calledOn = null)
    {
        $methodDefinition = $this->methods[$name] ?? null;

        if (! $methodDefinition) {
            return new UnknownType("Cannot get type of calling method [$name] on object [$this->name]");
        }

        $type = $methodDefinition->type;

        if (! $calledOn instanceof Generic) {
            return $type->getReturnType();
        }

        return $this->replaceTemplateInType($type, $calledOn->templateTypesMap)->getReturnType();
    }

    private function replaceTemplateInType(Type $type, array $templateTypesMap)
    {
        $type = clone $type;

        foreach ($templateTypesMap as $templateName => $templateValue) {
            (new TypeWalker)->replace(
                $type,
                fn ($t) => $t instanceof TemplateType && $t->name === $templateName ? $templateValue : null
            );
        }

        return $type;
    }
}
