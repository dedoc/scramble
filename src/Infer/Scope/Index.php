<?php

namespace Dedoc\Scramble\Infer\Scope;

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
    private array $functions = [];

    /**
     * @var array<string, ObjectType>
     */
    private array $classes = [];

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
}
