<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class FunctionLikeAstDefinition extends FunctionLikeDefinition
{
    use IndexAware;

    private ?FunctionLikeDefinition $declarationDefinition = null;

    public function setDeclarationDefinition(?FunctionLikeDefinition $declarationDefinition): self
    {
        $this->declarationDefinition = $declarationDefinition;

        return $this;
    }

    public function getDeclarationDefinition(): ?FunctionLikeDefinition
    {
        return $this->declarationDefinition;
    }

    public function getInferredReturnType(): Type
    {
        return $this->type->getReturnType();
    }

    public function getReturnType(): Type
    {
        $inferredReturnType = $this->type->getReturnType();

        if ($inferredReturnType->getAttribute('fromScrambleReturn') === true) {
            return $inferredReturnType;
        }

        if (! $returnDeclarationType = $this->getDeclarationDefinition()?->getReturnType()) {
            return $inferredReturnType;
        }

        return $this->prefersInferredReturnType($returnDeclarationType, $inferredReturnType)
            ? $inferredReturnType
            : $returnDeclarationType;
    }

    private function prefersInferredReturnType(?Type $declarationType, Type $inferredType): bool
    {
        if (! $declarationType || $declarationType instanceof UnknownType) {
            return true;
        }

        if ($declarationType->accepts($inferredType)) {
            return true;
        }

        if ($inferredType->acceptedBy($declarationType)) {
            return true;
        }

        return false;
    }
}
