<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Infer\Contracts\FunctionLikeDefinitionBuilder;
use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\FunctionLikeDefinition;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;

class FunctionLikeDeclarationAstDefinitionBuilder implements FunctionLikeDefinitionBuilder
{
    public function __construct(
        private FunctionLike $node,
        private ?ClassDefinition $classDefinition = null,
    ) {}

    public function build(): FunctionLikeDefinition
    {
        return new FunctionLikeDefinition(
            $this->buildType(),
            argumentsDefaults: $this->getArgumentDefaults(),
            definingClassName: $this->classDefinition?->name,
            isStatic: $this->node instanceof Node\Stmt\ClassMethod ? $this->node->isStatic() : false,
        );
    }

    private function buildType(): FunctionType
    {
        return new FunctionType(
            $this->getName(),
            arguments: $this->getArgumentTypes(),
            returnType: $this->getReturnType(),
        );
    }

    private function getReturnType(): Type
    {
        if (! $returnType = $this->node->getReturnType()) {
            return new UnknownType;
        }

        return TypeHelper::createTypeFromTypeNode($returnType);
    }

    /** @return array<string, Type> */
    private function getArgumentTypes(): array
    {
        return collect($this->node->getParams())
            ->mapWithKeys(function (Node\Param $param) {
                if (! $param->var instanceof Node\Expr\Variable || ! is_string($param->var->name)) {
                    return [];
                }

                $type = $param->type
                    ? TypeHelper::createTypeFromTypeNode($param->type)
                    : new MixedType;

                return [$param->var->name => $type];
            })
            ->all();
    }

    /** @return array<string, Type> */
    private function getArgumentDefaults(): array
    {
        return collect($this->node->getParams())
            ->mapWithKeys(function (Node\Param $param) {
                if (! $param->default) {
                    return [];
                }

                if (! $param->var instanceof Node\Expr\Variable || ! is_string($param->var->name)) {
                    return [];
                }

                return [
                    $param->var->name => (new GlobalScope)->getType($param->default),
                ];
            })
            ->filter()
            ->all();
    }

    private function getName(): string
    {
        if ($this->node instanceof ClassMethod || $this->node instanceof Node\Stmt\Function_) {
            return $this->node->name->name;
        }

        return '{anonymous}';
    }
}
