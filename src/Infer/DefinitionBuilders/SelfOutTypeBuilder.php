<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Infer\Services\TemplateTypesSolver;
use Dedoc\Scramble\Infer\UnresolvableArgumentTypeBag;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\SelfType;
use Dedoc\Scramble\Support\Type\TemplatePlaceholderType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

class SelfOutTypeBuilder
{
    public function __construct(
        private Scope $scope,
        private ClassMethod $node,
    ) {}

    public function build(): ?Generic
    {
        if (! $classDefinition = $this->scope->context->classDefinition) {
            return null;
        }

        if (! $functionDefinition = $this->scope->context->functionDefinition) {
            return null;
        }

        $expectedTemplatesMap = collect($classDefinition->templateTypes)
            ->mapWithKeys(fn (TemplateType $t) => [$t->name => null])
            ->all();

        $templateDefiningStatements = (new NodeFinder)->find(
            $this->node->stmts ?: [],
            fn ($n) => $this->isThisPropertyAssignment($n) // Direct assignments of something on `$this`, like `$this->foo = 42`.
                || ($functionDefinition->type->name === '__construct' && $this->isParentConstructCall($n)) // Calls to `parent::__construct` if is in constructor
                || ($this->isPotentialSetterCall($n) && $this->isSelfTypeOrCallOnSelfType($this->scope->getType($n->var)))// just any method call on $this (self type!)
        );

        foreach (array_reverse($templateDefiningStatements) as $statement) {
            if ($this->isThisPropertyAssignment($statement)) {
                $thisPropertiesAssignment = $statement;

                $propertyName = $thisPropertiesAssignment->var->name->name; // @phpstan-ignore property.notFound

                if (! array_key_exists($propertyName, $classDefinition->properties)) {
                    continue;
                }

                // if property name is not template type - skip
                $propertyType = $classDefinition->properties[$propertyName]->type;
                if (! $propertyType instanceof TemplateType || ! array_key_exists($propertyType->name, $expectedTemplatesMap)) {
                    continue;
                }

                // if property's template type is defined - skip
                if ($expectedTemplatesMap[$propertyType->name] !== null) {
                    continue;
                }

                // if property template type equals to assigned expression template type - skip
                $assignedType = $this->scope->getType($thisPropertiesAssignment->expr);
                if ($propertyType === $assignedType) {
                    continue;
                }

                // define template
                $expectedTemplatesMap[$propertyType->name] = $assignedType;

                continue;
            }

            if ($this->isParentConstructCall($statement)) {
                $parentConstructCall = $statement;

                if (! $classDefinition->parentFqn) {
                    continue;
                }

                if (! $parentDefinition = $this->scope->index->getClass($classDefinition->parentFqn)) {
                    continue;
                }

                $parentConstructor = $parentDefinition->getMethodDefinition('__construct');
                if (! $constructorSelfOutType = $parentConstructor?->getSelfOutType()) {
                    continue;
                }

                $parentCallContextTemplates = (new TemplateTypesSolver)->getClassConstructorContextTemplates(
                    $parentDefinition,
                    $parentDefinition->getMethodDefinition('__construct'),
                    $arguments = new UnresolvableArgumentTypeBag($this->scope->getArgsTypes($parentConstructCall->args)),
                );

                foreach ($constructorSelfOutType->templateTypes as $index => $genericSelfOutTypePart) {
                    if (! $definedParentTemplateType = ($parentDefinition->templateTypes[$index] ?? null)) {
                        continue;
                    }

                    // if property's template type is defined - skip
                    if (($expectedTemplatesMap[$definedParentTemplateType->name] ?? null) !== null) {
                        continue;
                    }

                    $concreteSelfOutTypePart = $genericSelfOutTypePart instanceof TemplatePlaceholderType && $parentCallContextTemplates->has($definedParentTemplateType->name)
                        ? $parentCallContextTemplates->get($definedParentTemplateType->name)
                        : (new TypeWalker)->map(
                            $genericSelfOutTypePart,
                            fn ($t) => $t instanceof TemplateType ? $parentCallContextTemplates->get($t->name, $t) : $t,
                        );

                    $expectedTemplatesMap[$definedParentTemplateType->name] = $concreteSelfOutTypePart;
                }

                continue;
            }

            // Potential setter calls analysis requires reference resolution!
            if ($this->isPotentialSetterCall($statement)) {
                $potentialSetterCall = $statement;

                /*
                 * When getting statements we made sure that `var` is either `$this`, or a call to
                 * a method on `$this` so resolving potential setter calls should not trigger entire codebase
                 * analysis (which would be slow).
                 */
                $var = ReferenceTypeResolver::getInstance()->resolve(
                    $this->scope,
                    $this->scope->getType($potentialSetterCall->var)->clone(),
                );

                if (! $var instanceof SelfType) {
                    continue;
                }

                if (! $methodDefinition = $classDefinition->getMethodDefinition($potentialSetterCall->name->name)) {  // @phpstan-ignore property.notFound
                    continue;
                }

                if (! $methodSelfOutType = $methodDefinition->getSelfOutType()) {
                    continue;
                }

                $methodCallContextTemplates = (new TemplateTypesSolver)->getFunctionContextTemplates(
                    $methodDefinition,
                    $arguments = new UnresolvableArgumentTypeBag($this->scope->getArgsTypes($potentialSetterCall->args)),
                );

                foreach ($methodSelfOutType->templateTypes as $index => $genericSelfOutTypePart) {
                    if ($genericSelfOutTypePart instanceof TemplatePlaceholderType) {
                        continue;
                    }

                    if (! $definedTemplateType = ($classDefinition->templateTypes[$index] ?? null)) {
                        continue;
                    }

                    // if property's template type is defined - skip
                    if (($expectedTemplatesMap[$definedTemplateType->name] ?? null) !== null) {
                        continue;
                    }

                    $concreteSelfOutTypePart = (new TypeWalker)->map(
                        $genericSelfOutTypePart,
                        fn ($t) => $t instanceof TemplateType ? $methodCallContextTemplates->get($t->name, $t) : $t,
                    );

                    $expectedTemplatesMap[$definedTemplateType->name] = $concreteSelfOutTypePart;
                }

                continue;
            }
        }

        return new Generic(
            'self',
            array_values(array_map(fn ($type) => $type ?: new TemplatePlaceholderType, $expectedTemplatesMap))
        );
    }

    /**
     * @phpstan-assert-if-true Assign $n
     */
    private function isThisPropertyAssignment(Node $n): bool
    {
        return $n instanceof Assign
            && $n->var instanceof PropertyFetch
            && $n->var->var instanceof Variable
            && $n->var->var->name === 'this'
            && $n->var->name instanceof Identifier;
    }

    /**
     * @phpstan-assert-if-true StaticCall $n
     */
    private function isParentConstructCall(Node $n): bool
    {
        return $n instanceof StaticCall
            && $n->class instanceof Name
            && $n->class->name === 'parent'
            && $n->name instanceof Identifier
            && $n->name->name === '__construct';
    }

    /**
     * @phpstan-assert-if-true MethodCall $n
     */
    private function isPotentialSetterCall(Node $n): bool
    {
        return $n instanceof MethodCall
            && $n->name instanceof Identifier;
    }

    private function isSelfTypeOrCallOnSelfType(Type $t): bool
    {
        if ($t instanceof SelfType) {
            return true;
        }

        if (! $t instanceof MethodCallReferenceType) {
            return false;
        }

        return $this->isSelfTypeOrCallOnSelfType($t->callee);
    }
}
