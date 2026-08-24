<?php

namespace Dedoc\Scramble\Support\ProNudge\Extensions;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\ProNudge\ProNudgeCollector;
use Dedoc\Scramble\Support\ProNudge\ProNudgeSignal;
use Dedoc\Scramble\Support\RouteInfo;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;

/**
 * Detects Spatie Query Builder usage by looking for class references in the
 * route action AST (StaticCall / ClassConstFetch / New_), without type-checking
 * every expression.
 *
 * @internal
 */
class QueryBuilderUsageNudgeExtension implements OperationTransformer
{
    private const QUERY_BUILDER_CLASS = 'Spatie\QueryBuilder\QueryBuilder';

    public function __construct(
        private ProNudgeCollector $collector,
    ) {}

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! $actionNode = $routeInfo->actionNode()) {
            return;
        }

        if (! $this->referencesQueryBuilder($actionNode)) {
            return;
        }

        $this->collector->record(ProNudgeSignal::QueryBuilder, $routeInfo);
    }

    private function referencesQueryBuilder(FunctionLike $actionNode): bool
    {
        return (new NodeFinder)->findFirst(
            $actionNode,
            fn (Node $node) => $this->isQueryBuilderClassReference($node),
        ) !== null;
    }

    private function isQueryBuilderClassReference(Node $node): bool
    {
        $class = match (true) {
            $node instanceof Node\Expr\ClassConstFetch,
            $node instanceof Node\Expr\StaticCall,
            $node instanceof Node\Expr\New_ => $node->class,
            default => null,
        };

        return $class instanceof Node\Name
            && class_exists(self::QUERY_BUILDER_CLASS)
            && is_a($class->toString(), self::QUERY_BUILDER_CLASS, true);
    }
}
