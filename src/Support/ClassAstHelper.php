<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\TypeInferringVisitor;
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

    public Scope $scope;

    public $namesResolver = null;

    private ?PhpDocNode $phpDoc = null;

    private array $extensions;

    private array $handlers;

    public function __construct(string $class, array $extensions = [], array $handlers = [])
    {
        $this->class = $class;
        $this->extensions = $extensions;
        $this->handlers = $handlers;

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
                && ($node->namespacedName ?? $node->name)->toString() === ltrim($this->class, '\\'),
        );

        $traverser = new NodeTraverser;
        $traverser->addVisitor($infer = new TypeInferringVisitor($this->namesResolver, $this->extensions, $this->handlers));
        $traverser->traverse([$classAst]);

        $this->scope = $infer->scope;

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

        $ns = ($nsNode = (new NodeFinder)->findFirst($fileAst, fn ($n) => $n instanceof Node\Stmt\Namespace_))
            ? $nsNode->name->toString()
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
