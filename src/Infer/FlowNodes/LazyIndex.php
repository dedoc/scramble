<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use ReflectionFunction;
use ReflectionParameter;
use Throwable;

class LazyIndex implements Index
{
    /**
     * @param array<string, FunctionType> $functions
     */
    public function __construct(
        private array $functions = [],
    )
    {
    }

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

        $parameters = collect($reflection->getParameters())
            ->mapWithKeys(fn (ReflectionParameter $p) => [
                $p->name => ($paramType = $p->getType())
                    ? TypeHelper::createTypeFromReflectionType($paramType)
                    : new MixedType,
            ])
            ->all();

        $returnType = ($retType = $reflection->getReturnType())
            ? TypeHelper::createTypeFromReflectionType($retType)
            : new UnknownType();

        return $this->functions[$name] = new FunctionType($name, $parameters, $returnType);
    }
}
