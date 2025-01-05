<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\AstLocator;
use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\SourceLocators\BypassAstLocator;
use Dedoc\Scramble\Infer\Symbol;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

class ClassShallowAstDefinitionBuilder implements ClassDefinitionBuilder
{
    public function __construct(
        private string $name,
        private AstLocator $astLocator,
    )
    {
    }

    public function build(): ClassDefinition
    {
        /** @var ClassLike $classNode */
        $classNode = $this->astLocator->getSource(Symbol::createForClass($this->name));
        if (! $classNode) {
            throw new \LogicException("Cannot locate [{$this->name}] class node in AST");
        }

        $parentDefinition = ($parentName = $this->getParentName($classNode))
            ? (new self($parentName, $this->astLocator))->build()
            : new ClassDefinition(name: '');

        $classDefinition = new ClassDefinition(
            name: $this->name,
            templateTypes: $parentDefinition->templateTypes,
            properties: array_map(fn ($pd) => clone $pd, $parentDefinition->properties ?: []),
            methods: $parentDefinition->methods ?: [],
            parentFqn: $parentName ?? null,
            parentClassDefinition: $parentDefinition->name ? $parentDefinition : null,
        );

        $traverser = new NodeTraverser(
        // new PhpParser\NodeVisitor\ParentConnectingVisitor(),
        // new \PhpParser\NodeVisitor\NameResolver(),
            new class ($classDefinition) extends NodeVisitorAbstract {
                public function __construct(private ClassDefinition $classDefinition) {}
                public function enterNode(Node $node)
                {
                    if ($node instanceof Node\Stmt\Property) {
                        foreach ($node->props as $prop) {
                            $this->classDefinition->properties[$prop->name->name] = new ClassPropertyDefinition(
                                type: $template = new TemplateType('T'.Str::ucfirst($prop->name->name), $node->type ? TypeHelper::createTypeFromTypeNode($node->type) : null),
                                defaultType: null, //@todo
                            );
                            $this->classDefinition->templateTypes[] = $template;
                        }
                        return NodeVisitor::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
                    }
                    if ($node instanceof Node\Stmt\ClassMethod) {
                        $this->classDefinition->methods[$node->name->name] = $method = new FunctionLikeDefinition(
                            (new FunctionLikeShallowAstDefinitionBuilder(
                                $node->name->name,
                                new BypassAstLocator($node),
                            ))->build()->getType(),
                            definingClassName: $this->classDefinition->name,
                            isStatic: $node->isStatic(),
                        );
                        return NodeVisitor::DONT_TRAVERSE_CURRENT_AND_CHILDREN;
                    }
                }
            },
        );
        $traverser->traverse([$classNode]);

        return $classDefinition;
    }

    private function getParentName(ClassLike $classNode): ?string
    {
        return $classNode instanceof Class_ ? $classNode->extends?->toString() : null;
    }
}
