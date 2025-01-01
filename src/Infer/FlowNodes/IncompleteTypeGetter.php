<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\VoidType;

class IncompleteTypeGetter
{
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
