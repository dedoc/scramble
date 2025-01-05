<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Illuminate\Support\LazyCollection;

class AbstractFlowNode implements FlowNode
{
    public function __construct(
        public array $antecedents,
    ) {}

    public function getAllAntecedents(): LazyCollection
    {
        return LazyCollection::make(function () {
            $visited = [];
            $queue = $this->antecedents;

            while (! empty($queue)) {
                $node = array_shift($queue);

                // Avoid revisiting nodes
                if (in_array($node, $visited, true)) {
                    continue;
                }

                yield $node;
                $visited[] = $node;

                // Append the antecedents of this node to the end of the queue
                $queue = array_merge($queue, $node->antecedents);
            }
        });
    }
}
