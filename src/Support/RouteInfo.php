<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Infer\Infer;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Routing\Route;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use ReflectionClass;
use ReflectionMethod;

class RouteInfo
{
    public Route $route;

    public ?FunctionType $methodType = null;

    private ?PhpDocNode $phpDoc = null;

    private ?ClassMethod $methodNode = null;

    public function __construct(Route $route, FileParser $fileParser, Infer $infer)
    {
        $this->route = $route;

        $this->initClassInfo($fileParser, $infer);
    }

    private function initClassInfo(FileParser $fileParser, Infer $infer)
    {
        if (! $this->isClassBased()) {
            return;
        }

        /*
         * This happens when the route is registered, but there is no method.
         */
        if (! $this->reflectionMethod()) {
            return;
        }

        $result = $fileParser->parse($this->reflectionMethod()->getFileName());

        $this->methodNode = $result->findMethod($this->route->getAction('uses'));

        $this->methodType = $infer
                ->analyzeClass($this->reflectionMethod()->getDeclaringClass()->getName())
                ->getMethodType($this->methodName());
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

        if (! $this->methodNode()) {
            return new PhpDocNode([]);
        }

        $this->phpDoc = $this->methodNode()->getAttribute('parsedPhpDoc') ?: new PhpDocNode([]);

        return $this->phpDoc;
    }

    public function methodNode(): ?ClassMethod
    {
        if ($this->methodNode || ! $this->isClassBased()) {
            return $this->methodNode;
        }

//        $content = 'class Foo { '.implode("\n", array_slice(
//            file($this->reflectionMethod()->getFileName()),
//            $this->reflectionMethod()->getStartLine() - 1,
//            $this->reflectionMethod()->getEndLine() - $this->reflectionMethod()->getStartLine() + 1,
//        )).'}';
//
//        dd($content);

//        $this->methodNode = $this->class->findFirstNode(
//            fn (Node $node) => $node instanceof Node\Stmt\ClassMethod && $node->name->name === $this->methodName(),
//        );

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
            ->sortByDesc(fn ($type) => count((new TypeWalker)->find($type, fn ($t) => ! $t instanceof UnknownType)))
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
        if (! $methodType = $this->getMethodType()) {
            return null;
        }

        return $methodType->getReturnType();
    }

    public function getMethodType(): ?FunctionType
    {
        if (! $this->isClassBased() || ! $this->methodNode()) {
            return null;
        }

        return $this->methodType;
    }
}
