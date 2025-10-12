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

class FunctionLikeDefinition
{
    public bool $isFullyAnalyzed = false;

    public bool $referencesResolved = false;

    private ?Generic $selfOutType;

    private ?Type $returnDeclarationType;

    private ?Type $returnPhpDocType;

    /**
     * @param  array<string, Type>  $argumentsDefaults  A map where the key is arg name and value is a default type.
     */
    public function __construct(
        public FunctionType $type,
        public array $argumentsDefaults = [],
        public ?string $definingClassName = null,
        public bool $isStatic = false,
        public ?SelfOutTypeBuilder $selfOutTypeBuilder = null,
    ) {
        $this->returnDeclarationType = new MissingType;
        $this->returnPhpDocType = new MissingType;
    }

    public function isFullyAnalyzed(): bool
    {
        return $this->isFullyAnalyzed;
    }

    public function addArgumentDefault(string $paramName, Type $type): self
    {
        $this->argumentsDefaults[$paramName] = $type;

        return $this;
    }

    public function getSelfOutType(): ?Generic
    {
        return $this->selfOutType ??= $this->selfOutTypeBuilder?->build();
    }

    public function getReturnDeclarationType(): ?Type
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

    public function getReturnPhpDocType(): ?Type
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
//                $inferredReturnType,$returnDeclarationType,
//                $inferredReturnType?->toString(),
//                $returnDeclarationType?->toString(),
//                ($returnDeclarationType && ! $returnDeclarationType->accepts($inferredReturnType) ? $returnDeclarationType : $inferredReturnType)->toString()
//            ]
//        ]);

        return $returnDeclarationType;
    }

    /**
     * When analyzing parent classes, function like definitions are "copied" from parent class. When function
     * like is sourced from AST, we don't want to make a deep clone to save some memory (is the difference really makes sense?)
     * as the definition will be re-build to the specifics of a given class. However, other types of fn definitions
     * may override this method and do a deeper cloning (or cloning at all!).
     */
    public function copyFromParent(): self
    {
        return $this;
    }
}
