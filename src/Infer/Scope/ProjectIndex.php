<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Illuminate\Support\Str;

class ProjectIndex implements IndexContract
{
    public function __construct(
        private IndexContract $reflectionIndex,
        private IndexContract $astIndex,
    ) {}

    public function getClass(string $name): ?ClassDefinitionContract
    {
        if ($this->shouldAnalyzeClassAst($name)) {
            return $this->astIndex->getClass($name);
        }

        return $this->reflectionIndex->getClass($name);
    }

    public function getFunction(string $name): ?FunctionLikeDefinition
    {
        if ($this->shouldAnalyzeFunctionAst($name)) {
            return $this->astIndex->getFunction($name);
        }

        return $this->reflectionIndex->getFunction($name);
    }

    private function shouldAnalyzeClassAst(string $name)
    {
        if (! $path = rescue(fn () => (new \ReflectionClass($name))->getFileName())) {
            return false;
        }

        return $this->shouldAnalyzeAst($path);
    }

    private function shouldAnalyzeFunctionAst(string $name)
    {
        if (! $path = rescue(fn () => (new \ReflectionFunction($name))->getFileName())) {
            return false;
        }

        return $this->shouldAnalyzeAst($path);
    }

    private function shouldAnalyzeAst(string $path)
    {
        return ! Str::contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
    }
}
