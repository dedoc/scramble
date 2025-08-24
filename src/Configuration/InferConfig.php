<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Infer\Configuration\ClassLike;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @internal
 */
class InferConfig
{
    private array $forceAnalyzingAst = [];

    /**
     * @return $this
     */
    public function buildDefinitionsUsingAstFor($items): static
    {
        $this->forceAnalyzingAst = [
            ...$this->forceAnalyzingAst,
            ...array_map(
                fn ($item) => is_string($item) ? new ClassLike($item) : $item,
                Arr::wrap($items),
            ),
        ];

        return $this;
    }

    public function shouldAnalyzeAst(string $class): bool
    {
        foreach ($this->forceAnalyzingAst as $item) {
            if ($item->matches($class)) {
                return true;
            }
        }

        $reflection = new \ReflectionClass($class);

        $path = $reflection->getFileName();

        if (! $path) {
            return true; // Keep in mind the internal classes are analyzed via AST analyzer
        }

        return ! Str::contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
    }
}
