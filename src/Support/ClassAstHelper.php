<?php

namespace Dedoc\Scramble\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\FirstFindingVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

class ClassAstHelper
{
    private string $class;

    public \ReflectionClass $classReflection;

    public Node\Stmt\Class_ $classAst;

    public $namesResolver;

    public function __construct(string $class)
    {
        $this->class = $class;
        $this->init();
    }

    private function init()
    {
        $this->classReflection = new \ReflectionClass($this->class);

        $fileAst = (new ParserFactory)->create(ParserFactory::PREFER_PHP7)->parse(file_get_contents($this->classReflection->getFileName()));

        [$classAst, $nameResolver] = $this->findFirstNode(
            fn (Node $node) => $node instanceof Node\Stmt\Class_
                && ($node->namespacedName ?? $node->name)->toString() === $this->class,
            $fileAst,
        );

        if (! $classAst) {
            throw new \InvalidArgumentException('Cannot create class AST of the class '.$this->class);
        }

        $this->classAst = $classAst;
        $this->namesResolver = $nameResolver;
    }

    public function getReturnNodeOfMethod(string $methodNodeName): ?Node\Stmt\Return_
    {
        /** @var Node\Stmt\ClassMethod|null $methodNode */
        [$methodNode] = $this->findFirstNode(
            fn (Node $node) => $node instanceof Node\Stmt\ClassMethod && $node->name->name === $methodNodeName
        );

        if (! $methodNode) {
            return null;
        }

        return (new NodeFinder())->findFirst(
            $methodNode,
            fn (Node $node) => $node instanceof Node\Stmt\Return_
        );
    }

    public function resolveFqName(string $class)
    {
        return ($this->namesResolver)($class);
    }

    public function findFirstNode(\Closure $param, $classAst = null)
    {
        if (! $classAst) {
            $classAst = $this->classAst;
        }
        $classAst = is_array($classAst) ? $classAst : [$classAst];

        $visitor = new FirstFindingVisitor($param);

        $nameResolver = new NameResolver();
        $traverser = new NodeTraverser;
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);
        $traverser->traverse($classAst);

        /** @var Node\Stmt\ClassMethod $methodNode */
        $methodNode = $visitor->getFoundNode();

        // @todo Fix dirty way of getting the map of aliases
        $context = $nameResolver->getNameContext();
        $reflection = new \ReflectionClass($context);
        $property = $reflection->getProperty('origAliases');
        $property->setAccessible(true);
        $value = $property->getValue($context);
        $ns = count($classAst) === 1 && $classAst[0] instanceof Node\Stmt\Namespace_
            ? $classAst[0]->name->toString()
            : null;
        $aliases = array_map(fn (Node\Name $n) => $n->toCodeString(), $value[1]);

        $getFqName = function (string $shortName) use ($ns, $aliases) {
            if (array_key_exists($shortName, $aliases)) {
                return $aliases[$shortName];
            }

            if ($ns && ($fqName = $ns.'\\'.$shortName) && class_exists($fqName)) {
                return $fqName;
            }

            return $shortName;
        };

        return [$methodNode, $getFqName];
    }
}
