<?php

namespace Dedoc\Scramble\Infer\Flow;

interface Node
{
    /** @return Edge[] */
    public function predecessors(): array;

    /** @return Edge[] */
    public function successors(): array;
}
