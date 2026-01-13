<?php

namespace Dedoc\Scramble\Infer\Flow;

interface Node
{
    public function toDotId(Nodes $nodes): string;

    public function toDot(Nodes $nodes): ?string;

    public function definesVariable(string $varName): bool;
}
