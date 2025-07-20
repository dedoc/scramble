<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Illuminate\Support\Str;

/**
 * Index stores type information about analyzed classes, functions, and constants.
 * The index exists per run and stores all the information, so it can be accessed
 * during analysis. Index contains all classes/fns/constants found in the analyzed file.
 */
class Index
{
    /**
     * @var array<string, ClassDefinition>
     */
    public array $classesDefinitions = [];

    /**
     * @var array<string, FunctionLikeDefinition>
     */
    public array $functionsDefinitions = [];

    public function registerClassDefinition(ClassDefinition $classDefinition): void
    {
        $this->classesDefinitions[$classDefinition->name] = $classDefinition;
    }

    public function getClassDefinition(string $className): ?ClassDefinition
    {
        if (isset($this->classesDefinitions[$className])) {
            return $this->classesDefinitions[$className];
        }

        $reflection = rescue(fn () => new \ReflectionClass($className)); // @phpstan-ignore argument.type

        if (! $reflection) {
            return null;
        }

        $classPath = $reflection->getFileName();

        if ($classPath && !static::shouldAnalyzeAst($classPath)) {
            Context::getInstance()->extensionsBroker->afterClassDefinitionCreated(new ClassDefinitionCreatedEvent($className, new ClassDefinition($className)));

            // The event emitted above MAY add the class definition to the index. So we'd like to return it if it was added.
            return $this->classesDefinitions[$className] ?? null;
        }

        /*
         * Keep in mind the internal classes are analyzed here due to $classPath being `null` for them.
         */
        return (new ClassAnalyzer($this))->analyze($className);
    }

    public static function shouldAnalyzeAst(string $path): bool
    {
        return ! Str::contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
    }

    public function registerFunctionDefinition(FunctionLikeDefinition $fnDefinition): void
    {
        $this->functionsDefinitions[$fnDefinition->type->name] = $fnDefinition;
    }

    public function getFunctionDefinition(string $fnName): ?FunctionLikeDefinition
    {
        return $this->functionsDefinitions[$fnName] ?? null;
    }
}
