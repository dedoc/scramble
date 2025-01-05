<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Analyzer\MethodAnalyzer;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\FlowNodes\AssignmentFlowNode;
use Dedoc\Scramble\Infer\FlowNodes\FlowNode;
use Dedoc\Scramble\Infer\FlowNodes\FlowNodeTypeGetter;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeGetter;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeResolver;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Scope\Index;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Scope\ScopeContext;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

class ClassDefinition implements ClassDefinitionContract
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
    ) {}

    public function isInstanceOf(string $className)
    {
        return is_a($this->name, $className, true);
    }

    public function isChildOf(string $className)
    {
        return $this->isInstanceOf($className) && $this->name !== $className;
    }

    public function hasMethodDefinition(string $name): bool
    {
        return array_key_exists($name, $this->methods);
    }

    public function getMethodDefinitionWithoutAnalysis(string $name)
    {
        if (! array_key_exists($name, $this->methods)) {
            return null;
        }

        return $this->methods[$name];
    }

    public function getMethodDefiningClassName(string $name, Index $index)
    {
        $lastLookedUpClassName = $this->name;
        while ($lastLookedUpClassDefinition = $index->getClassDefinition($lastLookedUpClassName)) {
            if ($methodDefinition = $lastLookedUpClassDefinition->getMethodDefinitionWithoutAnalysis($name)) {
                return $methodDefinition->definingClassName;
            }

            if ($lastLookedUpClassDefinition->parentFqn) {
                $lastLookedUpClassName = $lastLookedUpClassDefinition->parentFqn;

                continue;
            }

            break;
        }

        return $lastLookedUpClassName;
    }

    public function getMethod(string $name): ?FunctionLikeDefinition
    {
        if (! array_key_exists($name, $this->methods)) {
            return null;
        }
        return $this->methods[$name];
    }

    public function getData(): ClassDefinition
    {
        return $this;
    }

    public function getMethodDefinition(string $name, Scope $scope = new GlobalScope, array $indexBuilders = [])
    {
        if (! array_key_exists($name, $this->methods)) {
            return null;
        }

        $methodDefinition = $this->methods[$name];

        if (! $methodDefinition->isFullyAnalyzed()) {
            $this->methods[$name] = (new MethodAnalyzer(
                $scope->index,
                $this
            ))->analyze($methodDefinition, $indexBuilders);
        }

        $methodScope = new Scope(
            $scope->index,
            new NodeTypesResolver,
            new ScopeContext($this, $methodDefinition),
            new FileNameResolver(
                class_exists($this->name)
                    ? ClassReflector::make($this->name)->getNameContext()
                    : tap(new NameContext(new Throwing), fn (NameContext $nc) => $nc->startNamespace()),
            ),
        );

        (new ReferenceTypeResolver($scope->index))
            ->resolveFunctionReturnReferences($methodScope, $this->methods[$name]->type);

        foreach ($this->methods[$name]->type->exceptions as $i => $exceptionType) {
            if (ReferenceTypeResolver::hasResolvableReferences($exceptionType)) {
                $this->methods[$name]->type->exceptions[$i] = (new ReferenceTypeResolver($scope->index))
                    ->resolve($methodScope, $exceptionType)
                    ->mergeAttributes($exceptionType->attributes());
            }
        }

        return $this->methods[$name];
    }

    public function getPropertyDefinition($name)
    {
        return $this->properties[$name] ?? null;
    }

    public function hasPropertyDefinition(string $name): bool
    {
        return array_key_exists($name, $this->properties);
    }

    public function getMethodCallType(string $name, ?ObjectType $calledOn = null)
    {
        $methodDefinition = $this->methods[$name] ?? null;

        if (! $methodDefinition) {
            return new UnknownType("Cannot get type of calling method [$name] on object [$this->name]");
        }

        $type = $this->getMethodDefinition($name)->type;

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

    public function ensureFullyAnalyzed(\Dedoc\Scramble\Infer\Contracts\Index $index)
    {
        $parentDefinition = $this->parentFqn ? $index->getClass($this->parentFqn) : null;
        if ($parentDefinition) {
            $parentDefinition->ensureFullyAnalyzed($index);
        } else {
            $parentDefinition = new ClassDefinition('');
        }

        if (!$constructFunction = $this->getMethodDefinitionWithoutAnalysis('__construct')) {
            return;
        }

        if (! $flowContainer = $constructFunction->type->getAttribute('ast')?->getAttribute('flowContainer') ?? null) {
            return;
        }

        $constructFunctionType = (new IncompleteTypeGetter())->getFunctionType($constructFunction->type->getAttribute('ast'), '__construct');

        // traverse setters and assignments from the bottom up
        /** @var FlowNode $node */
        $propertiesAssignmentsMap = [];
        foreach (array_reverse($flowContainer->nodes) as $node) {
            if ($node instanceof AssignmentFlowNode && $node->kind === AssignmentFlowNode::KIND_ASSIGN) {
                /** @var Assign $assign */
                $assign = $node->expression;

                $isAssignToThisProperty = $assign->var instanceof PropertyFetch
                    && $assign->var->var instanceof Variable
                    && $assign->var->var->name === 'this'
                    && $assign->var->name instanceof Identifier;

                if (! $isAssignToThisProperty) {
                    continue;
                }

                // If the type is already in properties, no need to analyze as we're traversing from the end
                // and the latest assignment is the one that remains.
                if (array_key_exists($assign->var->name->name, $propertiesAssignmentsMap)) {
                    continue;
                }

                $propertiesAssignmentsMap = array_merge([
                    $assign->var->name->name => (new FlowNodeTypeGetter($node->expression, $node))->getType(),
                ], $propertiesAssignmentsMap);
            }
        }

        $resolvedConstructFunctionType = clone $constructFunctionType;

        // given the properties assignment map, update constructor type (use resolvedType) and template defaults
        $reassignedTemplates = [];
        foreach ($this->properties as $name => $propertyDefinition) {
            // this may happen in parent class is manually annotated (??)
            if (! $propertyDefinition->type instanceof TemplateType) {
                continue;
            }

            if (! array_key_exists($name, $propertiesAssignmentsMap)) {
                continue;
            }

            $assignedType = $propertiesAssignmentsMap[$name];

            $hasHandledParam = false;
            foreach ($resolvedConstructFunctionType->arguments as &$parameterType) { // naming issue
                // if the assigned type is fully equal to a parameter type, replace the parameter type with property type
                // and remove local constructor type
                if ($parameterType === $assignedType) {
                    $parameterType = $propertyDefinition->type;
                    $reassignedTemplates[$assignedType->name] = $parameterType;
                    $hasHandledParam = true;
                    break;
                }
            }
            if ($hasHandledParam) {
                continue;
            }


            // if the assigned type is not those equal to param, store the value as default template type
            // remove template from constructor definition if after resolution the local template type is
            // not encountered in a constructor type (or exception TODO)
            $resolvedAssignedType = (new IncompleteTypeResolver($index))->resolve($assignedType);
            // in case we're assigning the template type that has been already associated with a template type, we'd want to
            // use it to describe the resulting type
            $resolvedAssignedType = (new TypeWalker)->map(
                $resolvedAssignedType,
                fn (Type $t) => $t instanceof TemplateType
                    ? $reassignedTemplates[$t->name] ?? $t
                    : $t,
            );
            $propertyDefinition->type->default = $resolvedAssignedType;
        }

        // delete templates from $resolvedConstructFunctionType that are not encountered in class template defaults or
        // in the exceptions
        $templatesReferencedInClassTemplates = $this->templateTypes;
        foreach ($this->templateTypes as $templateType) {
            if (! $templateType->default) {
                continue;
            }
            $defaultsTemplates = (new TypeWalker)->findAll(
                $templateType->default,
                fn (Type $t) => $t instanceof TemplateType,
            );
            $templatesReferencedInClassTemplates = array_merge($templatesReferencedInClassTemplates, $defaultsTemplates);
        }
        $resolvedConstructFunctionType->templates = array_values(array_filter(
            $resolvedConstructFunctionType->templates,
            fn ($t) => in_array($t, $templatesReferencedInClassTemplates),
        ));
        $resolvedConstructFunctionType->arguments = array_map(
            fn ($t) => $t instanceof TemplateType
                ? in_array($t, $templatesReferencedInClassTemplates) ? $t : ($t->is ?: new MixedType)
                : $t,
            $resolvedConstructFunctionType->arguments
        );

        $constructFunctionType->setAttribute('resolvedType', $resolvedConstructFunctionType);
        $constructFunction->type = $constructFunctionType;
    }
}
