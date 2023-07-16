<?php

namespace Dedoc\Scramble\Support\Type\SideEffects;

use Dedoc\Scramble\Support\Type\Type;

class SelfTemplateDefinition
{
    public function __construct(
        public string $definedTemplate,
        public Type $type,
    ) {
    }
}
