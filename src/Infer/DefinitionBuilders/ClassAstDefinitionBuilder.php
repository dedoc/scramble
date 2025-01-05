<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\AstLocator;
use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition as FunctionLikeDefinitionContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition as ClassDefinitionData;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\FlowNodes\AssignmentFlowNode;
use Dedoc\Scramble\Infer\FlowNodes\FlowNode;
use Dedoc\Scramble\Infer\FlowNodes\FlowNodeTypeGetter;
use Dedoc\Scramble\Infer\FlowNodes\TemplateTypeNameGetter;
use Dedoc\Scramble\Infer\SourceLocators\BypassAstLocator;
use Dedoc\Scramble\Infer\Symbol;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

class ClassAstDefinitionBuilder implements ClassDefinitionBuilder
{
    public function __construct(
        private string $name,
        private AstLocator $astLocator,
    )
    {
    }

    /**
     * @return Node\Stmt[]
     */
    private function getClassMembersNodes(ClassLike $node): array
    {
        $visitor = new class extends NodeVisitorAbstract {
            public function __construct(public array $members = [])
            {
            }
            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Property) {
                    $this->members[] = $node;
                    return NodeVisitor::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
                }
                if ($node instanceof Node\Stmt\ClassMethod) {
                    $this->members[] = $node;
                    return NodeVisitor::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
                }
            }
        };
        $traverser = new NodeTraverser(
            // new PhpParser\NodeVisitor\ParentConnectingVisitor(),
            // new \PhpParser\NodeVisitor\NameResolver(),
            $visitor,
        );
        $traverser->traverse([$node]);
        return $visitor->members;
    }

    public function build(): ClassDefinitionData
    {
        $classNode = $this->astLocator->getSource(Symbol::createForClass($this->name));
        if (! $classNode) {
            throw new \LogicException("Cannot locate [{$this->name}] class node in AST");
        }
        $classMembers = $this->getClassMembersNodes($classNode);

        $classDefinition = new ClassDefinitionData(name: $this->name);
        $templateTypeNamesGetter = new TemplateTypeNameGetter($classDefinition);

        $methodNodes = [];
        foreach ($classMembers as $node) {
            if ($node instanceof Node\Stmt\Property) {
                foreach ($node->props as $prop) {
                    $classDefinition->properties[$prop->name->name] = new ClassPropertyDefinition(
                        type: $template = new TemplateType('T'.Str::ucfirst($prop->name->name), $node->type ? TypeHelper::createTypeFromTypeNode($node->type) : null),
                        defaultType: null, //@todo
                    );
                    $classDefinition->templateTypes[] = $template;
                }
            }

            if ($node instanceof Node\Stmt\ClassMethod) {
                $methodNodes[] = $node;
            }
        }

        foreach ($methodNodes as $methodNode) {
            $this->buildMethodDefinitionAndUpdateClassDefinitionData(
                $classDefinition,
                $methodNode,
            );

        }

        return $classDefinition;
    }

    public function buildMethodDefinitionAndUpdateClassDefinitionData(
        ClassDefinitionData $classDefinition,
        Node\Stmt\ClassMethod $methodNode,
    ): FunctionLikeDefinitionContract
    {
        $methodDefinition = (new FunctionLikeAstDefinitionBuilder(
            $methodNode->name->name,
            new BypassAstLocator($methodNode),
        ))->build();

        $classDefinition->methods[$methodNode->name->name] = $methodDefinition;

        if ($methodNode->name->name === '__construct') {
            $this->redefineClassDefinitionWithConstructorBody($classDefinition, $methodNode);
        }

        return $methodDefinition;
    }

    private function redefineClassDefinitionWithConstructorBody(ClassDefinitionData $classDefinition, Node\Stmt\ClassMethod $constructorNode)
    {
        // @todo what are we doing with parents here???

        if (! $flowContainer = $constructorNode->getAttribute('flowContainer') ?? null) {
            return;
        }

        $constructFunction = $classDefinition->getMethod('__construct');
        $constructFunctionType = $constructFunction->getType();

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
        foreach ($classDefinition->properties as $name => $propertyDefinition) {
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
            $assignedType = (new TypeWalker)->map(
                $assignedType,
                fn (Type $t) => $t instanceof TemplateType
                    ? $reassignedTemplates[$t->name] ?? $t
                    : $t,
            );

            $propertyDefinition->type->default = $assignedType;
        }

        $constructFunction->type = $resolvedConstructFunctionType;
        // By setting the attribute, we prevent the constructor method from being resolved (at it will remove template types from args)
        $resolvedConstructFunctionType->setAttribute('resolvedType', $resolvedConstructFunctionType);
    }
}
