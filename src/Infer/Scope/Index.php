<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\ObjectType;

/**
 * Index stores type information about analyzed classes, functions, and constants.
 * The index exists per run and stores all the information, so it can be accessed
 * during analysis. Index contains all classes/fns/constants found in the analyzed file.
 */
class Index
{
    /**
     * @var array<string, FunctionType>
     */
    public array $functions = [];

    /**
     * @var array<string, ObjectType>
     */
    public array $classes = [];

    /**
     * @var array<string, ClassDefinition>
     */
    public array $classesDefinitions = [];

    public function registerFunctionType(string $fnName, FunctionType $type): void
    {
        $this->functions[$fnName] = $type;
    }

    public function getFunctionType(string $fnName): ?FunctionType
    {
        return $this->functions[$fnName] ?? null;
    }

    public function registerClassType(string $className, ObjectType $type): void
    {
        $this->classes[$className] = $type;
    }

    public function getClassType(string $className): ?ObjectType
    {
        return $this->classes[$className] ?? null;
    }

    public function registerClassDefinition(ClassDefinition $classDefinition): void
    {
        $this->classesDefinitions[$classDefinition->name] = $classDefinition;
    }

    public function getClassDefinition(string $className): ?ClassDefinition
    {
        return $this->classesDefinitions[$className] ?? null;
    }

    public function registerFunctionDefinition(FunctionLikeDefinition $fnDefinition)
    {
        $this->functionsDefinitions[$fnDefinition->type->name] = $fnDefinition;
    }
}
