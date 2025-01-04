<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\FunctionLikeReflectionDefinitionBuilder;
use Dedoc\Scramble\Infer\Reflection\ReflectionFunction as ScrambleReflectionFunction;
use Dedoc\Scramble\Support\Type\FunctionType;
use Illuminate\Support\Str;
use PhpParser\Parser;
use ReflectionFunction;
use Throwable;

class LazyIndex implements Index
{
    /**
     * @param array<string, FunctionType> $functions
     * @param array<string, ClassDefinition> $classes
     */
    public function __construct(
        private Parser $parser,
        private array $functions = [],
        private array $classes = [],
    )
    {}

    public function getFunction(string $name): ?FunctionType
    {
        if (isset($this->functions[$name])) {
            return $this->functions[$name];
        }

        try {
            $reflection = new ReflectionFunction($name);
        } catch (Throwable) {
            return null;
        }

        $reflectionFunction = ScrambleReflectionFunction::createFromName($name, $this, $this->parser);

        if (
            ($filePath = $reflection->getFileName())
            && $this->shouldAnalyzeAstByPath($filePath)
        ) {
            try {
                $functionDefinition = $reflectionFunction->getDefinition();
            } catch (\LogicException $e) {
                // @todo log/dump
                $functionDefinition = null;
            }

            if ($functionDefinition) {
                return $this->functions[$name] = $functionDefinition->getType();
            }

            return null;
        }

        return $this->functions[$name] = (new FunctionLikeReflectionDefinitionBuilder($reflectionFunction))->build()->getType();
    }

    public function getClass(string $name): ?ClassDefinition
    {
        if (isset($this->classes[$name])) {
            return $this->classes[$name];
        }

        // @todo shallowly analyze

        return null;
    }

    private function shouldAnalyzeAstByPath(string $filePath): bool
    {
        return ! Str::contains($filePath, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
    }
}
