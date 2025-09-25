<?php

namespace Dedoc\Scramble\Infer\Reflector;

use Closure;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;
use Dedoc\Scramble\Support\IndexBuilders\RequestParametersBuilder;
use Dedoc\Scramble\Support\IndexBuilders\ScopeCollector;
use Laravel\SerializableClosure\Support\ReflectionClosure;
use PhpParser\NameContext;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use ReflectionMethod;
use WeakMap;

class ClosureReflector
{
    /**
     * @var WeakMap<Closure, self>
     */
    private static WeakMap $cache;

    private ?NameContext $nameContext = null;

    private ?Node\FunctionLike $astNode = null;

    private function __construct(
        private FileParser $parser,
        public Closure $closure,
    ) {
    }

    /**
     * @return WeakMap<Closure, self>
     */
    private static function getCache(): WeakMap
    {
        /** @var WeakMap<Closure, self> $default */
        $default = new WeakMap;

        return self::$cache ??= $default;
    }

    public static function make(Closure $closure): self
    {
        if (self::getCache()->offsetExists($closure)) {
            return self::getCache()->offsetGet($closure);
        }

        $reflector = new self(app(FileParser::class), $closure);

        self::getCache()->offsetSet($closure, $reflector);

        return $reflector;
    }

    public function getCode(): string
    {
        return $this->getReflection()->getCode();
    }

    public function getNameContext(): NameContext
    {
        if (! $path = $this->getReflection()->getFileName()) {
            throw new \LogicException('Cannot find file name for closure');
        }

        return $this->nameContext ??= FileNameResolver::createForFile($path)->nameContext;
    }

    public function getReflection(): ReflectionClosure
    {
        return new ReflectionClosure($this->closure);
    }

    public function getAstNode(): ?Node\FunctionLike
    {
        if ($this->astNode) {
            return $this->astNode;
        }

        $code = '<?php '.$this->getCode().';';

        /** @var Node\FunctionLike|null $node */
        $node = (new NodeFinder)
            ->findFirst(
                $this->parser->parseContent($code)->getStatements(),
                fn (Node $node) => $node instanceof Node\FunctionLike,
            );

        if (! $node) {
            return null;
        }

        if (! $path = $this->getReflection()->getFileName()) {
            return null;
        }

        $fileNameContext = FileNameResolver::createForFile($path);

        $traverser = new NodeTraverser(
            new class($fileNameContext->nameContext) extends NameResolver
            {
                public function __construct(NameContext $nameContext)
                {
                    parent::__construct();
                    $this->nameContext = $nameContext;
                }

                public function beforeTraverse(array $nodes): ?array
                {
                    return null;
                }
            },
            new PhpDocResolver($fileNameContext),
        );
        $traverser->traverse([$node]);

        return $this->astNode = $node;
    }

    /**
     * @param IndexBuilder<array<string, mixed>>[] $indexBuilders
     */
    public function getFunctionLikeDefinition(array $indexBuilders = [], bool $withSideEffects = false): FunctionLikeDefinition
    {
        if (! $functionLikeNode = $this->getAstNode()) {
            throw new \LogicException('Cannot get AST node of closure');
        }

        $scopeCollector = new ScopeCollector;

        $closureDefinition = (new Infer\DefinitionBuilders\FunctionLikeAstDefinitionBuilder(
            '{closure}',
            $functionLikeNode,
            app(Infer::class)->index,
            new FileNameResolver($this->getNameContext()),
            indexBuilders: [...$indexBuilders, $scopeCollector],
            withSideEffects: $withSideEffects,
        ))->build();

        if (! $scope = $scopeCollector->getScope($closureDefinition)) {
            throw new \LogicException('Cannot get scope of closure');
        }

        Infer\Definition\ClassDefinition::resolveFunctionReturnReferences($scope, $closureDefinition->type);
        Infer\Definition\ClassDefinition::resolveFunctionExceptions($scope, $closureDefinition->type);

        $closureDefinition->referencesResolved = true;

        return $closureDefinition;
    }
}
