<?php

namespace Dedoc\Scramble\Support\IndexBuilders;

use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\Scope;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use WeakMap;

/**
 * @implements IndexBuilder<array<string, mixed>>
 */
class ScopeCollector implements IndexBuilder
{
    /** @var WeakMap<FunctionLikeDefinition, Scope> */
    public static WeakMap $scopes;

    public function __construct()
    {
        if (! isset(static::$scopes)) {
            static::$scopes = new WeakMap;
        }
    }

    public function afterAnalyzedNode(Scope $scope, Node $node): void
    {
        if (
            $node instanceof FunctionLike
            && $scope->context->functionDefinition
        ) {
            static::$scopes->offsetSet($scope->context->functionDefinition, $scope);
        }
    }

    public function getScope(FunctionLikeDefinition $definition): ?Scope
    {
        return static::$scopes->offsetExists($definition)
            ? static::$scopes->offsetGet($definition)
            : null;
    }
}
