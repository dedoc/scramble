<?php

namespace Dedoc\Scramble\Support;

use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\FirstFindingVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class ClassAstHelper
{
    private string $class;

    public \ReflectionClass $classReflection;

    public Node\Stmt\Class_ $classAst;

    public $namesResolver = null;

    private ?PhpDocNode $phpDoc = null;

    public function __construct(string $class)
    {
        $this->class = $class;
        $this->init();
    }

    private function init()
    {
        $this->classReflection = new \ReflectionClass($this->class);

        $fileAst = (new ParserFactory)->create(ParserFactory::PREFER_PHP7)->parse(file_get_contents($this->classReflection->getFileName()));

        $this->namesResolver = $this->extractNamesResolver($fileAst);

        $classAst = (new NodeFinder())->findFirst(
            $fileAst,
            fn (Node $node) => $node instanceof Node\Stmt\Class_
                && ($node->namespacedName ?? $node->name)->toString() === $this->class,
        );

        if (! $classAst) {
            throw new \InvalidArgumentException('Cannot create class AST of the class '.$this->class);
        }

        $this->classAst = $classAst;
    }

    public function phpDoc(): PhpDocNode
    {
        if ($this->phpDoc) {
            return $this->phpDoc;
        }

        $this->phpDoc = new PhpDocNode([]);

        if ($docComment = $this->classReflection->getDocComment()) {
            $this->phpDoc = PhpDoc::parse($docComment);
        }

        return $this->phpDoc;
    }

    public function getReturnNodeOfMethod(string $methodNodeName): ?Node\Stmt\Return_
    {
        /** @var Node\Stmt\ClassMethod|null $methodNode */
        $methodNode = $this->findFirstNode(
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

    private function extractNamesResolver($fileAst)
    {
        $fileAst = Arr::wrap($fileAst);

        $traverser = new NodeTraverser;
        $nameResolver = new NameResolver();
        $traverser->addVisitor($nameResolver);
        $traverser->traverse($fileAst);

        $context = $nameResolver->getNameContext();
        $reflection = new \ReflectionClass($context);
        $property = $reflection->getProperty('origAliases');
        $property->setAccessible(true);
        $value = $property->getValue($context);
        $ns = count($fileAst) === 1 && $fileAst[0] instanceof Node\Stmt\Namespace_
            ? $fileAst[0]->name->toString()
            : '\\';
        $aliases = array_map(fn (Node\Name $n) => $n->toCodeString(), $value[1]);

        $getFqName = function (string $shortName) use ($ns, $aliases) {
            if (array_key_exists($shortName, $aliases)) {
                return $aliases[$shortName];
            }

            if ($ns && ($fqName = rtrim($ns.'\\'.$shortName, '\\')) && class_exists($fqName)) {
                return $fqName;
            }

            return $shortName;
        };

        return $getFqName;
    }

    public function findFirstNode(\Closure $param, $classAst = null)
    {
        if (! $classAst) {
            $classAst = $this->classAst;
        }
        $classAst = is_array($classAst) ? $classAst : [$classAst];

        $visitor = new FirstFindingVisitor($param);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($classAst);

        return $visitor->getFoundNode();
    }
}
