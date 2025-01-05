<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\AstLocator;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\ClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Symbol;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\VoidType;
use Illuminate\Support\Str;
use PhpParser\Node;
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

    public function build(): ClassDefinitionContract
    {
        $classNode = $this->astLocator->getSource(Symbol::createForClass($this->name));
        if (! $classNode) {
            throw new \LogicException("Cannot locate [{$this->name}] class node in AST");
        }

        $classDefinition = new ClassDefinition(
            name: $this->name,
        );
        /** @var ClassLike $classNode */

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
                            new FunctionType($node->name->name, returnType: new VoidType),
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
}
