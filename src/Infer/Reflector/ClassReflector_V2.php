<?php

namespace Dedoc\Scramble\Infer\Reflector;

use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\FlowNodes\FlowBuildingVisitor;
use Dedoc\Scramble\Infer\FlowNodes\IncompleteTypeGetter;
use Dedoc\Scramble\Infer\FlowNodes\TemplateTypeNameGetter;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\VoidType;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

class ClassReflector_V2
{
    public function __construct(
//        private readonly Index $index,
        public readonly string $name,
        private readonly string $code, // @todo
    )
    {
    }

    public static function makeFromCodeString(string $name, string $code): static
    {
//        $sourceLocator = new CodeStringSourceLocator($code);

        return new static(
//            $index,
            $name,
//            $sourceLocator->getFunctionAst($name),
            $code,
        );
    }

    public function getDefinition(): ClassDefinition
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse($this->code);
        $classNode = (new NodeFinder)->findFirst(
            $ast,
            fn ($n) => $n instanceof ClassLike && (($n->name->name ?? null) === $this->name),
        );
        if (! $classNode) {
            throw new \LogicException("Cannot locate [$this->name] class node in AST");
        }

        $classDefinition = new ClassDefinition(
            name: $this->name,
        );
        $templateTypeNamesGetter = new TemplateTypeNameGetter($classDefinition);
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
                    }
                    if ($node instanceof Node\Stmt\ClassMethod) {
                        $this->classDefinition->methods[$node->name->name] = $method = new FunctionLikeDefinition(
                            new FunctionType($node->name->name, returnType: new VoidType),
                            definingClassName: $this->classDefinition->name,
                            isStatic: $node->isStatic(),
                        );
                        $method->type->setAttribute('ast', $node);
                    }
                }
            },
        );
        $traverser->addVisitor($flowVisitor = new FlowBuildingVisitor(
            $traverser,
            $templateTypeNamesGetter,
        ));

        $traverser->traverse([$classNode]);

//        $incompleteFunctionType = (new IncompleteTypeGetter())->getFunctionType($functionNode, $this->name);
//
//        if (! $incompleteFunctionType) {
//            throw new \LogicException("Cannot locate flow node container in [$this->name] function AST node");
//        }

        return $classDefinition;
    }
}
