<?php

namespace Dedoc\Scramble\Configuration;

use Composer\Autoload\ClassLoader;
use Dedoc\Scramble\Infer\Configuration\ClassLike;
use Dedoc\Scramble\Infer\Configuration\DefinitionMatcher;
use Dedoc\Scramble\Infer\Context;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @internal
 */
class InferConfig
{
    /** @var DefinitionMatcher[] */
    private array $forcedAstSourcedDefinitionsMatchers = [];

    /** @var DefinitionMatcher[] */
    private array $forcedReflectionSourcedDefinitionsMatchers = [];

    /**
     * @param  (string|DefinitionMatcher)[]  $items
     * @return $this
     */
    public function buildDefinitionsUsingAstFor(array $items): static
    {
        $this->forcedAstSourcedDefinitionsMatchers = [
            ...$this->forcedAstSourcedDefinitionsMatchers,
            ...array_map(
                fn ($item) => is_string($item) ? new ClassLike($item) : $item,
                Arr::wrap($items),
            ),
        ];

        return $this;
    }

    /**
     * @param  (string|DefinitionMatcher)[]  $items
     * @return $this
     */
    public function buildDefinitionsUsingReflectionFor(array $items): static
    {
        $this->forcedReflectionSourcedDefinitionsMatchers = [
            ...$this->forcedReflectionSourcedDefinitionsMatchers,
            ...array_map(
                fn ($item) => is_string($item) ? new ClassLike($item) : $item,
                Arr::wrap($items),
            ),
        ];

        return $this;
    }

    /**
     * @param  DefinitionMatcher[]  $matchers
     */
    private function matchesAnyDefinitionMatcher(string $class, array $matchers): bool
    {
        foreach ($matchers as $item) {
            if ($item->matches($class)) {
                return true;
            }
        }

        return false;
    }

    public function shouldAnalyzeAst(string $class): bool
    {
        if ($this->matchesAnyDefinitionMatcher($class, $this->forcedReflectionSourcedDefinitionsMatchers)) {
            return false;
        }

        if ($this->matchesAnyDefinitionMatcher($class, $this->forcedAstSourcedDefinitionsMatchers)) {
            return true;
        }

        // Ignoring static analysis error due to it is fine to exception to be thrown here if bad input.
        $reflection = new \ReflectionClass($class); // @phpstan-ignore argument.type

        $path = $reflection->getFileName();

        if (! $path) {
            return true; // Keep in mind the internal classes are analyzed via AST analyzer
        }

        if (Str::contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
            return false;
        }

        foreach ($this->vendorDirectories() as $vendorDirectory) {
            if (Str::startsWith($path, $vendorDirectory.DIRECTORY_SEPARATOR)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The vendor directories of the registered Composer autoloaders, which differ from `vendor`
     * when a project sets Composer's `vendor-dir`.
     *
     * @return string[]
     */
    protected function vendorDirectories(): array
    {
        if (! class_exists(ClassLoader::class)) {
            return [];
        }

        return array_keys(ClassLoader::getRegisteredLoaders());
    }

    public function replaceExtensions(array $inferExtensions): self
    {
        Context::getInstance()->extensionsBroker->replaceExtensions($inferExtensions);

        return $this;
    }
}
