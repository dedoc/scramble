<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use ReflectionParameter;

class FunctionLikeReflectionDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    public function __construct(
        public string $name,
        private \ReflectionFunction|\ReflectionMethod|null $reflection = null
    ) {
        if (! $this->reflection) {
            $this->reflection = new \ReflectionFunction($this->name);
        }
    }

    public function build(): FunctionLikeDefinition
    {
        $parameters = collect($this->reflection->getParameters())
            ->mapWithKeys(fn (ReflectionParameter $p) => [
                $p->name => ($paramType = $p->getType())
                    ? TypeHelper::createTypeFromReflectionType($paramType)
                    : new MixedType,
            ])
            ->all();

        $returnType = ($retType = $this->reflection->getReturnType())
            ? TypeHelper::createTypeFromReflectionType($retType)
            : new UnknownType;

        $type = new FunctionType($this->name, $parameters, $returnType);

        return new FunctionLikeDefinition($type);
    }
}
