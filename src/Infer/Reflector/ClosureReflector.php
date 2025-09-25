<?php

namespace Dedoc\Scramble\Infer\Reflector;

use Closure;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Infer\Services\FileParser;
use Dedoc\Scramble\Infer\Visitors\PhpDocResolver;
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
    private static WeakMap $cache;

    private ?NameContext $nameContext = null;

    private ?Node\FunctionLike $astNode = null;

    private function __construct(
        private FileParser $parser,
        public Closure $closure,
    ) {
    }

    private static function getCache(): WeakMap
    {
        return self::$cache ??= new WeakMap;
    }

    public static function make(Closure $closure)
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
        return $this->nameContext ??= FileNameResolver::createForFile($this->getReflection()->getFileName())->nameContext;
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

        $node = (new NodeFinder)
            ->findFirst(
                $this->parser->parseContent($code)->getStatements()[0],
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

    public function getFunctionLikeDefinition(array $indexBuilders = [], bool $withSideEffects = false): FunctionLikeDefinition
    {
        $scopeCollector = new ScopeCollector;

        $closureDefinition = (new Infer\DefinitionBuilders\FunctionLikeAstDefinitionBuilder(
            '{closure}',
            $this->getAstNode(),
            app(Infer::class)->index,
            new FileNameResolver($this->getNameContext()),
            indexBuilders: [...$indexBuilders, $scopeCollector],
            withSideEffects: $withSideEffects,
        ))->build();

        $scope = $scopeCollector->getScope($closureDefinition);

        Infer\Definition\ClassDefinition::resolveFunctionReturnReferences($scope, $closureDefinition->type);
        Infer\Definition\ClassDefinition::resolveFunctionExceptions($scope, $closureDefinition->type);

        $closureDefinition->referencesResolved = true;

        return $closureDefinition;
    }
}
