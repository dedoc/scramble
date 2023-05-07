<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;

class AutoresolvingClassDefinition extends ClassDefinition
{
    public function __construct(
        private ReferenceTypeResolver $referencedsTypeResolver,
        string $name,
        array $templateTypes = [],
        array $properties = [],
        array $methods = [],
        ?string $parentFqn = null,
    ) {
        parent::__construct($name, $templateTypes, $properties, $methods, $parentFqn);
    }

    public function getMethodDefinition(string $name)
    {
        if (! $methodDefinition = parent::getMethodDefinition($name)) {
            return $methodDefinition;
        }

        $methodDefinition->type->setReturnType(
            $this->referenceTypeResolver->resolve(new GlobalScope, $methodDefinition->type->getReturnType()),
        );

        return $methodDefinition;
    }
}
