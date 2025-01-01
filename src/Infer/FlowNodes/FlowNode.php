<?php

namespace Dedoc\Scramble\Infer\FlowNodes;

use Illuminate\Support\LazyCollection;

interface FlowNode
{
    public function getAllAntecedents(): LazyCollection;
}
