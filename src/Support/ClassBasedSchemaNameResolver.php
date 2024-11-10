<?php

namespace Dedoc\Scramble\Support;

use Closure;

/**
 * @phpstan-type NameResolver Closure(string, array): ?string
 * @internal
 */
class ClassBasedSchemaNameResolver
{
    /** @var NameResolver[] */
    private array $nameResolverStack = [];

    public function __construct()
    {
        $this->nameResolverStack[] = function (string $className): string {
            return $className;
        };
    }

    public function resolve(string $className, array $context = []): string
    {
        /** @var NameResolver $nameResolver */
        foreach (array_reverse($this->nameResolverStack) as $nameResolver) {
            if ($name = $nameResolver($className, $context)) {
                return $name;
            }
        }
        // Should never happen as the very first name
        throw new \Exception('No class based name resolver resolved a schema name.');
    }

    /**
     * @param NameResolver $cb
     * @return $this
     */
    public function addNameResolver(Closure $cb): self
    {
        $this->nameResolverStack[] = $cb;

        return $this;
    }
}
