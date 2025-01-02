<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\VoidType;
use PhpParser\Node\FunctionLike;

/**
 * @internal
 */
class IncompleteTypeGetter
{
    public function getFunctionType(FunctionLike $node, string $name): ?FunctionType
    {
        if (! $flowContainer = $node->getAttribute('flowContainer')) {
            return null;
        }

        /** @var FlowNodes $flowContainer */

        // @todo make sure template types are not conflicting with parent template types!!!

        $returnType = $this->getFunctionReturnType($flowContainer->nodes);
        [$templates, $parameters] = $flowContainer->nodes[0] instanceof EnterFunctionLikeFlowNode
            ? $flowContainer->nodes[0]->getParametersTypesDeclaration()
            : [[], []];

        $type = new FunctionType($name, $parameters, $returnType);
        $type->templates = $templates;
        return $type;
    }

    public function getFunctionReturnType(array $flowNodes)
    {
        $returnFlowNodes = collect($flowNodes)
            ->filter(fn (FlowNode $flow) => $flow instanceof TerminateFlowNode && $flow->kind === TerminateFlowNode::KIND_RETURN)
            ->filter($this->isReachableFlowNode(...))
            ->values();

        if (! $returnFlowNodes->count()) {
            return new VoidType;
        }

        return Union::wrap(
            $returnFlowNodes
                ->map(fn (TerminateFlowNode $flowNode) => (new FlowNodeTypeGetter($flowNode->expression, $flowNode))->getType())
                ->values()
                ->all()
        );
    }

    protected function isReachableFlowNode(FlowNode $flowNode)
    {
        return $flowNode->getAllAntecedents()->contains(fn (FlowNode $f) => $f instanceof EnterFunctionLikeFlowNode);
    }
}
