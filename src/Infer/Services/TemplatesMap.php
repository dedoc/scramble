<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\RecursiveTemplateSolver;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;

class TemplatesMap
{
    private const ARGUMENTS = 'Arguments';

    public array $additional = [];

    /**
     * @param TemplateType[] $templates
     * @param array<string, Type> $parameters
     * @param ArgumentTypeBag $arguments
     * @param array<string, Type> $defaults
     */
    public function __construct(
        public array $templates,
        public array $parameters,
        public ArgumentTypeBag $arguments,
        public array $defaults,
    ) {}

    /**
     * @param  array<string, Type>  $additional
     * @return $this
     */
    public function prepend(array $additional): self
    {
        $this->additional = array_merge($additional, $this->additional);

        return $this;
    }

    public function get(string $name, Type $defaultType = new UnknownType): Type
    {
        $result = $this->getF($name, $defaultType);
//        dump([$name => $result->toString()]);
        return $result;
    }

    public function getF(string $name, Type $defaultType = new UnknownType): Type
    {
        if ($name === self::ARGUMENTS) {
            return new KeyedArrayType(collect($this->arguments->all())->map(fn ($t, $k) => new ArrayItemType_($k, $t))->all());
        }

        return $this->getSingle($name) ?? $this->additional[$name] ?? $defaultType;
    }

    protected function getSingle(string $name): ?Type
    {
        $template = collect($this->templates)->first(fn (TemplateType $t) => $t->name === $name);

        if (! $template) {
            return null;
        }

        foreach (array_values($this->parameters) as $i => $parameterType) {
            if (! $this->hasTemplateIn($parameterType, $template)) {
                continue;
            }

            $name = array_keys($this->parameters)[$i];

            $argumentType = $this->arguments->get($name, $i, $this->defaults[$name] ?? null) ?: new UnknownType;

            if ($inferredType = $this->inferTemplate($template, $parameterType, $argumentType)) {
                return $inferredType;
            }
        }

        return null;
    }

    private function hasTemplateIn(Type $parameterType, TemplateType $templateType): bool
    {
        return (bool) (new TypeWalker)->first($parameterType, fn ($t) => $t === $templateType);
    }

    public function has(string $name): bool
    {
        return $name === self::ARGUMENTS
            || array_key_exists($name, $this->additional)
            || (bool) collect($this->templates)->first(fn ($t) => $t->name === $name);
    }

    private function inferTemplate(TemplateType $template, Type $typeWithTemplate, Type $type): ?Type
    {
        return (new RecursiveTemplateSolver)->solve($typeWithTemplate, $type, $template);
    }
}
