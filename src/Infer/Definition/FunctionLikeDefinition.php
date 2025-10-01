<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Analyzer\MethodAnalyzer;
use Dedoc\Scramble\Infer\DefinitionBuilders\SelfOutTypeBuilder;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;

class FunctionLikeDefinition
{
    public bool $isFullyAnalyzed = false;

    public bool $referencesResolved = false;

    private ?Generic $_selfOutType;

    /**
     * @param  array<string, Type>  $argumentsDefaults  A map where the key is arg name and value is a default type.
     */
    public function __construct(
        public FunctionType $type,
        public array $argumentsDefaults = [],
        public ?string $definingClassName = null,
        public bool $isStatic = false,
        public ?SelfOutTypeBuilder $selfOutTypeBuilder = null,
    ) {}

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
        return $this->_selfOutType ??= $this->selfOutTypeBuilder?->build();
    }

    public function getReturnType(): Type
    {
//        if (! $methodDefinition->isFullyAnalyzed()) {
//            $this->methods[$name] = (new MethodAnalyzer(
//                $scope->index,
//                $this,
//            ))->analyze($methodDefinition, $indexBuilders, $withSideEffects);
//        }
//
//        if (! $this->referencesResolved) { // @todo make a part of !$methodDefinition->isFullyAnalyzed() (a part of method definition building)
//            $methodScope = new Scope(
//                $scope->index,
//                new NodeTypesResolver,
//                new ScopeContext(new ClassDefinition($this->definingClassName), $methodDefinition),
//                new FileNameResolver(
//                    class_exists($this->definingClassName)
//                        ? ClassReflector::make($this->definingClassName)->getNameContext()
//                        : tap(new NameContext(new Throwing), fn(NameContext $nc) => $nc->startNamespace()),
//                ),
//            );
//
//            ClassDefinition::resolveFunctionReturnReferences($methodScope, $this);
//
//            ClassDefinition::resolveFunctionExceptions($methodScope, $this);
//
//            $this->referencesResolved = true;
//        }

        return $this->type->getReturnType();
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
