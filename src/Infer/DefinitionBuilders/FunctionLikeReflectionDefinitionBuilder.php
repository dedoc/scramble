<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition as FunctionLikeDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Reflection\ReflectionFunction;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use ReflectionParameter;

class FunctionLikeReflectionDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    public function __construct(public readonly ReflectionFunction $reflectionFunction)
    {
    }

    public function build(): FunctionLikeDefinitionContract
    {
        $reflection = new \ReflectionFunction($this->reflectionFunction->name);

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

        $type = new FunctionType($this->reflectionFunction->name, $parameters, $returnType);

        return new FunctionLikeDefinition($type);
    }
}
