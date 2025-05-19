<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\AstClassDefinitionBuilder;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Index stores type information about analyzed classes, functions, and constants.
 * The index exists per run and stores all the information, so it can be accessed
 * during analysis. Index contains all classes/fns/constants found in the analyzed file.
 */
class Index implements IndexContract
{
    public function __construct(
        public array $classes = [],
        public array $functions = [],
    )
    {
    }

    public function registerClassDefinition(ClassDefinition $classDefinition): void
    {
        $this->classes[$classDefinition->name] = $classDefinition;
    }

    public function getClassDefinition(string $className): ?ClassDefinition
    {
        return $this->classes[$className] ?? null;
    }

    public function registerFunctionDefinition(FunctionLikeDefinition $fnDefinition)
    {
        $this->functions[$fnDefinition->type->name] = $fnDefinition;
    }

    public function getFunctionDefinition(string $fnName): ?FunctionLikeDefinition
    {
        return $this->functions[$fnName] ?? null;
    }

    public function getFunction(string $name): ?FunctionLikeDefinition
    {
        return $this->getFunctionDefinition($name);
    }

    public function getClass(string $name): ?ClassDefinitionContract
    {
        if (isset($this->classes[$name])) {
            return $this->classes[$name];
        }

        $reflectionClass =  rescue(fn () => new ReflectionClass($name));

        if (! $reflectionClass) {
            return null;
        }

        $path = $reflectionClass->getFileName();

        if (! $this->shouldAnalyzeAst($path)) {
            Context::getInstance()->extensionsBroker->afterClassDefinitionCreated(new ClassDefinitionCreatedEvent($name, new ClassDefinition($name)));

            return $this->getClassDefinition($name);
        }

        return $this->classes[$name] = (new AstClassDefinitionBuilder($this, $reflectionClass))->build();
    }

    protected function shouldAnalyzeAst(string $path): bool
    {
        return ! Str::contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
    }
}
