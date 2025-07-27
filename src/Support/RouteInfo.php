<?php

namespace Dedoc\Scramble\Support;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Reflector\MethodReflector;
use Dedoc\Scramble\Support\IndexBuilders\Bag;
use Dedoc\Scramble\Support\IndexBuilders\RequestParametersBuilder;
use Dedoc\Scramble\Support\IndexBuilders\ScopeCollector;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\InferredParameter;
use Dedoc\Scramble\Support\Type\FunctionType;
use Illuminate\Routing\Route;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

class RouteInfo
{
    public ?FunctionType $methodType = null;

    private ?PhpDocNode $phpDoc = null;

    private ?ClassMethod $methodNode = null;

    private ?Infer\Scope\Scope $scope = null;

    /** @var Bag<array<string, InferredParameter>> */
    public readonly Bag $requestParametersFromCalls;

    public readonly Infer\Extensions\IndexBuildingBroker $indexBuildingBroker;

    public function __construct(
        public readonly Route $route,
        private Infer $infer,
    ) {
        /** @var Bag<array<string, InferredParameter>> $bag */
        $bag = new Bag;
        $this->requestParametersFromCalls = $bag;
        $this->indexBuildingBroker = app(Infer\Extensions\IndexBuildingBroker::class);
    }

    public function isClassBased(): bool
    {
        return is_string($this->route->getAction('uses'));
    }

    public function className(): ?string
    {
        return $this->isClassBased()
            ? ltrim(explode('@', $this->route->getAction('uses'))[0], '\\')
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

        return $this->methodNode = MethodReflector::make(...explode('@', $this->route->getAction('uses')))
            ->getAstNode();
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

    public function getReturnType()
    {
        return (new RouteResponseTypeRetriever($this))->getResponseType();
    }

    /**
     * @todo Maybe better name is needed as this method performs method analysis, indexes building, etc.
     */
    public function getMethodType(): ?FunctionType
    {
        if (! $this->isClassBased() || ! $this->reflectionMethod()) {
            return null;
        }

        if (! $this->methodType) {
            $def = $this->infer->analyzeClass($this->className());

            $scopeCollector = new ScopeCollector;

            /*
             * Sometimes method type may be null if route registered method name has the casing that
             * is different from the method name in the controller hence reflection is used here.
             */
            $this->methodType = ($methodDefinition = $def->getMethodDefinition(
                $this->reflectionMethod()->getName(),
                indexBuilders: [
                    new RequestParametersBuilder($this->requestParametersFromCalls),
                    $scopeCollector,
                    ...$this->indexBuildingBroker->indexBuilders,
                ],
                withSideEffects: true,
            ))?->type;

            if ($methodDefinition) {
                $this->scope = $scopeCollector->getScope($methodDefinition);
            }
        }

        return $this->methodType;
    }

    /** @internal */
    public function getScope(): Infer\Scope\Scope
    {
        if (! $this->scope) {
            throw new RuntimeException('Scope is not initialized for route. Make sure to call `getMethodType` before calling `getScope`');
        }

        return $this->scope;
    }
}
