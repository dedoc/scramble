<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinition as FunctionLikeDefinitionContract;
use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Reflection\ReflectionFunction;
use Dedoc\Scramble\Infer\Symbol;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\NodeFinder;
use PhpParser\Parser;

class FunctionLikeShallowAstDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    public function __construct(
        public readonly ReflectionFunction $reflectionFunction,
        public readonly Parser $parser,
    )
    {
    }

    public function build(): FunctionLikeDefinitionContract
    {
        $source = $this->reflectionFunction->sourceLocator->getSource(Symbol::createForFunction($this->reflectionFunction->name));

        $ast = $this->parser->parse($source);
        $functionNode = (new NodeFinder)->findFirst(
            $ast,
            fn ($n) => $n instanceof FunctionLike && (($n->name->name ?? null) === $this->reflectionFunction->name),
        );
        if (! $functionNode) {
            throw new \LogicException("Cannot locate [{$this->reflectionFunction->name}] function node in AST");
        }

        // @todo make sure to resolve the names here!!!

        /** @var FunctionLike $functionNode */

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

        $type = new FunctionType($this->reflectionFunction->name, $parameters, $returnType);

        return new FunctionLikeDefinition($type);
    }
}
