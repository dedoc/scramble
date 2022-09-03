<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\PhpDoc\PhpDocTypeWalker;
use Dedoc\Scramble\PhpDoc\ResolveFqnPhpDocTypeVisitor;
use Dedoc\Scramble\Support\Infer\TypeInferringVisitor;
use Dedoc\Scramble\Support\Type\FunctionLikeType;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use ReflectionClass;
use ReflectionMethod;

class RouteInfo
{
    public Route $route;

    public ?ClassAstHelper $class = null;

    private ?PhpDocNode $phpDoc = null;

    private ?ClassMethod $methodNode = null;

    public function __construct(Route $route)
    {
        $this->route = $route;
        $this->initClassInfo();
    }

    private function initClassInfo()
    {
        if (! $this->isClassBased()) {
            return;
        }

        $this->class = new ClassAstHelper($this->className());
    }

    public function isClassBased(): bool
    {
        return is_string($this->route->getAction('uses'));
    }

    public function className(): ?string
    {
        return $this->isClassBased()
            ? explode('@', $this->route->getAction('uses'))[0]
            : null;
    }

    public function methodName(): ?string
    {
        return $this->isClassBased()
            ? explode('@', $this->route->getAction('uses'))[1]
            : null;
    }

    public function phpDoc(): PhpDocNode
    {
        if ($this->phpDoc) {
            return $this->phpDoc;
        }

        $this->phpDoc = new PhpDocNode([]);

        if ($docComment = optional($this->reflectionMethod())->getDocComment()) {
            $this->phpDoc = PhpDoc::parse($docComment);
            $this->addPhpDocAttributes($this->phpDoc);
        }

        if (count($returnTagValues = $this->phpDoc->getReturnTagValues())) {
            foreach ($returnTagValues as $returnTagValue) {
                if (! $returnTagValue->type) {
                    continue;
                }
                PhpDocTypeWalker::traverse($returnTagValue->type, [new ResolveFqnPhpDocTypeVisitor($this->class->namesResolver)]);
            }
        }

        return $this->phpDoc;
    }

    public function methodNode(): ?ClassMethod
    {
        if ($this->methodNode || ! $this->isClassBased()) {
            return $this->methodNode;
        }

        $this->methodNode = $this->class->findFirstNode(
            fn (Node $node) => $node instanceof Node\Stmt\ClassMethod && $node->name->name === $this->methodName(),
        );

        if ($this->methodNode) {
            $traverser = new NodeTraverser;
            $traverser->addVisitor(new TypeInferringVisitor($this->class->namesResolver));
            $traverser->traverse([$this->methodNode]);
        }

        return $this->methodNode;
    }

    public function reflectionMethod(): ?ReflectionMethod
    {
        if (! $this->isClassBased()) {
            return null;
        }

        if (! method_exists($this->className(), $this->methodName())) {
            return null;
        }

        return (new ReflectionClass($this->className()))
            ->getMethod($this->methodName());
    }

    public function getReturnTypes()
    {
        return collect([
            ($phpDocType = $this->getDocReturnType()) ? PhpDocTypeHelper::toType($phpDocType) : null,
            $this->getCodeReturnType(),
        ])
            ->filter()
            // Make sure the type with more leafs is first one.
            ->sortByDesc(fn ($type) => count((new TypeWalker)->find($type, fn ($t) => !$t instanceof UnknownType)))
            ->values()
            ->all();
    }

    public function getReturnType()
    {
        if ($phpDocType = $this->getDocReturnType()) {
            return $phpDocType;
        }

        return $this->getCodeReturnType();
    }

    public function getDocReturnType()
    {
        if ($this->phpDoc() && ($returnType = $this->phpDoc()->getReturnTagValues()[0] ?? null) && optional($returnType)->type) {
            return $returnType->type;
        }

        return null;
    }

    public function getCodeReturnType()
    {
        if (! $this->isClassBased() || ! $this->methodNode()) {
            return null;
        }

        /** @var FunctionLikeType $methodType */
        $methodType = $this->class->scope->getType($this->methodNode());

        return $methodType->getReturnType();
    }

    private function addPhpDocAttributes(PhpDocNode $phpDoc)
    {
        $text = collect($phpDoc->children)
            ->filter(fn ($v) => $v instanceof PhpDocTextNode)
            ->map(fn (PhpDocTextNode $n) => $n->text)
            ->implode("\n");

        $text = Str::of($text)
            ->trim()
            ->explode("\n\n", 2);

        $phpDoc->setAttribute('summary', $text[0] ?? '');
        $phpDoc->setAttribute('description', $text[1] ?? '');
    }
}
