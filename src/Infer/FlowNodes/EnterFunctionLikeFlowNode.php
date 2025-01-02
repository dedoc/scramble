<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Illuminate\Support\Str;
use PhpParser\Node\ClosureUse;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use WeakMap;

class EnterFunctionLikeFlowNode extends AbstractFlowNode
{
    private WeakMap $typesCache;

    public function __construct(
        public readonly array $parameters,
        public readonly FunctionLike $node,
        public readonly ?FlowNode $containerAntecedent,
        array $antecedents,
    )
    {
        parent::__construct($antecedents);
        $this->typesCache = new WeakMap;
    }

    public function hasAccessToParent(Expr $expression): bool
    {
        if (! $expression instanceof Expr\Variable) {
            return false;
        }

        if ($this->node instanceof Expr\ArrowFunction) {
            return true;
        }

        return collect($this->node->uses)
            ->contains(function (ClosureUse $use) use ($expression) {
                return is_string($use->var->name) && $use->var->name === $expression->name;
            });
    }

    public function hasParameter(Expr $expression): bool
    {
        return !! $this->getParameter($expression);
    }

    public function getParameter(Expr $expression): ?Param
    {
        if (! $expression instanceof Expr\Variable) {
            return null;
        }

        if (! is_string($expression->name)) {
            return null;
        }

        return collect($this->parameters)->first(
            fn (Param $p) => $p->var instanceof Expr\Variable && $p->var->name === $expression->name
        );
    }

    public function getParameterType(Param $parameter)
    {
        if ($this->typesCache->offsetExists($parameter)) {
            return $this->typesCache->offsetGet($parameter);
        }

        $templateType = new TemplateType('T'.Str::ucfirst($parameter->var->name));

        if ($parameter->type) {
            $templateType->is = TypeHelper::createTypeFromTypeNode($parameter->type);
        }

        $this->typesCache->offsetSet($parameter, $templateType);

        return $templateType;
    }

    public function getParametersTypesDeclaration()
    {
        $parameters = collect($this->parameters)
            ->mapWithKeys(function (Param $p) {
                if (
                    ! $p->var instanceof Expr\Variable
                    || ! is_string($p->var->name)
                ) {
                    return [];
                }

                return [$p->var->name => $this->getParameterType($p)];
            })
            ->all();

        $templateTypes = collect($parameters)
            ->values()
            ->whereInstanceOf(TemplateType::class)
            ->all();

        return [$templateTypes, $parameters];
    }
}
