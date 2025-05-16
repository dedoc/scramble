<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Infer\Contracts\ClassDefinition as ClassDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\Index as IndexContract;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;

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

    public function getFunctionDefinition(string $fnName): ?FunctionLikeDefinition
    {
        return $this->functionsDefinitions[$fnName] ?? null;
    }

    public function getFunction(string $name): ?FunctionLikeDefinition
    {
        return $this->getFunctionDefinition($name);
    }

    public function getClass(string $name): ?ClassDefinitionContract
    {
        return $this->getClassDefinition($name);
    }
}
