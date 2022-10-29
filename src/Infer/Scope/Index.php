<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Support\Type\FunctionType;

/**
 * Index stores type information about analyzed classes, functions, and constants.
 * The index exists per run and stores all the information, so it can be accessed
 * during analysis.
 */
class Index
{
    /**
     * @var array<string, FunctionType>
     */
    private array $functions = [];

    public function registerFunctionType(string $fnName, FunctionType $type): void
    {
        $this->functions[$fnName] = $type;
    }

    public function getFunctionType(string $fnName): ?FunctionType
    {
        return $this->functions[$fnName] ?? null;
    }
}
