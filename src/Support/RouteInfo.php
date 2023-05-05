<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\NodeTypesResolver;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Routing\Route;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use ReflectionClass;
use ReflectionMethod;

class RouteInfo
{
    public Route $route;

    public ?FunctionType $methodType = null;

    private ?PhpDocNode $phpDoc = null;

    private ?ClassMethod $methodNode = null;

    private FileParser $parser;

    private Infer $infer;

    public function __construct(Route $route, FileParser $fileParser, Infer $infer)
    {
        $this->route = $route;
        $this->parser = $fileParser;
        $this->infer = $infer;
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
        if ($this->methodNode || ! $this->isClassBased() || ! $this->reflectionMethod()) {
            return $this->methodNode;
        }

        $result = $this->parser->parse($this->reflectionMethod()->getFileName());

        return $this->methodNode = $result->findMethod($this->route->getAction('uses'));
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

        if (! $this->methodType) {
            $this->infer->analyzeClass($className = $this->reflectionMethod()->getDeclaringClass()->getName(), [
                $this->methodName(),
            ]);

            /*
             * Here the final resolution of the method types may happen.
             */
            $this->methodType = (new ObjectType($className))
                ->getMethodDefinition($this->methodName())
                ->type;
        }

        return $this->methodType;
    }
}
