<?php

namespace Dedoc\Scramble\Support\GroupTree;

use Dedoc\Scramble\Support\Generator\Operation;
use Illuminate\Routing\Route;

/**
 * Runs an ordered set of {@see GroupResolverStrategy} instances and returns the
 * first non-empty group path. A terminal strategy (typically
 * {@see FallbackGroupResolver}) is expected to guarantee a non-empty result.
 */
class GroupResolverPipeline
{
    /** @var list<GroupResolverStrategy> */
    protected array $strategies;

    /**
     * @param  iterable<GroupResolverStrategy>  $strategies
     */
    public function __construct(iterable $strategies = [])
    {
        $this->strategies = is_array($strategies) ? array_values($strategies) : iterator_to_array($strategies, false);

        if ($this->strategies === []) {
            $this->strategies = self::defaultStrategies();
        }
    }

    /**
     * The default, ordered resolution strategies.
     *
     * @return list<GroupResolverStrategy>
     */
    public static function defaultStrategies(): array
    {
        return [
            new AttributeGroupResolver,
            new ConfigGroupResolver,
            new RouteGroupResolver,
            new FallbackGroupResolver,
        ];
    }

    /**
     * Register an additional strategy.
     */
    public function registerStrategy(GroupResolverStrategy $strategy, bool $prepend = false): void
    {
        if ($prepend) {
            array_unshift($this->strategies, $strategy);
        } else {
            $this->strategies[] = $strategy;
        }
    }

    /**
     * Resolve the group path hierarchy for the given operation and route.
     *
     * @return list<string>
     */
    public function resolve(Operation $operation, Route $route): array
    {
        foreach ($this->strategies as $strategy) {
            $path = $strategy->resolve($operation, $route);

            if ($path !== null && $path !== []) {
                return $path;
            }
        }

        return [];
    }
}
