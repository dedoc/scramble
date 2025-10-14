<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\DefinitionBuilders\SelfOutTypeBuilder;
use Dedoc\Scramble\Infer\Reflector\MethodReflector;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\MissingType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\StaticReference;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;

class FunctionLikeAstDefinition extends FunctionLikeDefinition
{
    private ?Type $returnDeclarationType;

    private ?Type $returnPhpDocType;

    public function init(): void
    {
        $this->returnDeclarationType = new MissingType;
        $this->returnPhpDocType = new MissingType;
    }

    public function getReturnType(): Type
    {
        $inferredReturnType = $this->type->getReturnType();

        $returnDeclarationType = $this->getReturnPhpDocType() ?? $this->getReturnDeclarationType();

        if (! $returnDeclarationType) {
            return $inferredReturnType;
        }

        if ($returnDeclarationType->accepts($inferredReturnType) || $inferredReturnType->acceptedBy($returnDeclarationType)) {
            return $inferredReturnType;
        }

//        dump([
//            "$this->definingClassName@{$this->type->name}" => [
////                $inferredReturnType,$returnDeclarationType,
//                $inferredReturnType?->toString(),
//                $returnDeclarationType?->toString(),
//                ($returnDeclarationType && ! $returnDeclarationType->accepts($inferredReturnType) ? $returnDeclarationType : $inferredReturnType)->toString()
//            ]
//        ]);

        return $returnDeclarationType;
    }

    protected function getReturnDeclarationType(): ?Type
    {
        if (! $this->returnDeclarationType instanceof MissingType) {
            return $this->returnDeclarationType;
        }

        if (! $this->definingClassName) {
            return $this->returnDeclarationType = null;
        }

        /** @var \ReflectionMethod $reflection */
        $reflection = rescue(
            fn () => MethodReflector::make($this->definingClassName, $this->type->name)->getReflection(),
            report: false,
        );

        if (! $reflection) {
            return $this->returnDeclarationType = null;
        }

        if (! $reflection->getReturnType()) {
            return $this->returnDeclarationType = null;
        }

        $returnDeclarationType = TypeHelper::createTypeFromReflectionType($reflection->getReturnType());

        if ($returnDeclarationType instanceof ObjectType && $returnDeclarationType->name === StaticReference::SELF) {
            $returnDeclarationType = new ObjectType($this->definingClassName);
        }

        return $this->returnDeclarationType = $returnDeclarationType;
    }

    protected function getReturnPhpDocType(): ?Type
    {
        if (! $this->returnPhpDocType instanceof MissingType) {
            return $this->returnPhpDocType;
        }

        if (! $this->definingClassName) {
            return $this->returnPhpDocType = null;
        }

        $reflector = MethodReflector::make($this->definingClassName, $this->type->name);

        /** @var \ReflectionMethod $reflection */
        $reflection = rescue(fn () => $reflector->getReflection(), report: false);

        if (! $reflection) {
            return $this->returnPhpDocType = null;
        }

        if (! $docComment = $reflection->getDocComment()) {
            return $this->returnPhpDocType = null;
        }

        $phpDocNode = PhpDoc::parse(
            $docComment,
            new FileNameResolver($reflector->getClassReflector()->getNameContext()),
        );

        if ($phpDocNode->getReturnTagValues('@scramble-return')) {
            return $this->returnPhpDocType = null;
        }

        $returnType = (new FunctionLikeReflectionDefinitionBuilder(
            $this->type->name,
            $reflection,
            collect(app(Index::class)->getClass($this->definingClassName)?->templateTypes ?: [])->keyBy->name,
        ))->build()->type->getReturnType();

        if ($returnType instanceof UnknownType) {
            return $this->returnPhpDocType = null;
        }

        return $this->returnPhpDocType = $returnType;
    }
}
