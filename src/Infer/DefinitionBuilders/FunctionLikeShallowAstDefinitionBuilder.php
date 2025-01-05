<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\AstLocator;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition as FunctionLikeDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Symbol;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;

class FunctionLikeShallowAstDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    public function __construct(
        public readonly string $name,
        public readonly AstLocator $astLocator,
    ) {}

    public function build(): FunctionLikeDefinitionContract
    {
        /** @var FunctionLike $functionNode */
        $functionNode = $this->astLocator->getSource(Symbol::createForFunction($this->name));
        if (! $functionNode) {
            throw new \LogicException("Cannot locate [{$this->name}] function node in AST");
        }
        // @todo make sure to resolve the names here!!!

        $parameters = collect($functionNode->getParams())
            ->mapWithKeys(fn (Param $p) => [ // param->var may be an error
                $p->var->name => $p->type
                    ? TypeHelper::createTypeFromTypeNode($p->type)
                    : new MixedType,
            ])
            ->all();

        $returnType = ($retType = $functionNode->getReturnType())
            ? TypeHelper::createTypeFromTypeNode($retType)
            : new MixedType;

        $type = new FunctionType($this->name, $parameters, $returnType);

        return new FunctionLikeDefinition($type);
    }
}
