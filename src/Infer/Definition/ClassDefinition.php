<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Support\Type\TemplateType;

class ClassDefinition
{
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

    public function isInstanceOf(string $className)
    {
        return is_a($this->name, $className, true);
    }
}
