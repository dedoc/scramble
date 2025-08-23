<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Analyzer\ClassAnalyzer;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\DefinitionBuilders\ShallowClassReflectionDefinitionBuilder;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;

/**
 * Index stores type information about analyzed classes, functions, and constants.
 * The index exists per run and stores all the information, so it can be accessed
 * during analysis. Index contains all classes/fns/constants found in the analyzed file.
 */
class Index implements IndexContract
{
    /**
     * @var array<string, ClassDefinition>
     */
    public array $classesDefinitions = [];

    /**
     * @var array<string, FunctionLikeDefinition>
     */
    public array $functionsDefinitions = [];

    /**
     * @internal
     * @var class-string<object>[]
     */
    public static array $avoidAnalyzingAstClasses = [];

    public function getClass(string $className): ?ClassDefinition
    {
        if (isset($this->classesDefinitions[$className])) {
            return $this->classesDefinitions[$className];
        }

        $reflection = $this->createClassReflection($className);

        if (! $reflection) {
            return null;
        }

        $classPath = $reflection->getFileName();

        // @todo: $avoidAnalyzingAstClasses is needed for testing only, fix it!
        if (
            $classPath
            && ! ($shouldAnalyzeAst = ! in_array($className, static::$avoidAnalyzingAstClasses) && static::shouldAnalyzeAst($classPath))
        ) {
            return $this->classesDefinitions[$className] = (new ShallowClassReflectionDefinitionBuilder($this, $reflection))->build();
        }

        /*
         * Keep in mind the internal classes are analyzed here due to $classPath being `null` for them.
         */
        return (new ClassAnalyzer($this))->analyze($className);
    }

    public function registerClassDefinition(ClassDefinition $classDefinition): void
    {
        $this->classesDefinitions[$classDefinition->name] = $classDefinition;
    }

    public function getClassDefinition(string $className): ?ClassDefinition
    {
        return $this->classesDefinitions[$className] ?? null;
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

    /** @return ?ReflectionClass<object> */
    private function createClassReflection(string $className): ?ReflectionClass
    {
        try {
            return new ReflectionClass($className); // @phpstan-ignore argument.type
        } catch (ReflectionException) {
            return null;
        }
    }

    public function getFunction(string $name): ?FunctionLikeDefinition
    {
        return $this->getFunctionDefinition($name);
    }
}
