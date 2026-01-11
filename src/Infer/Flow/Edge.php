<?php

namespace Dedoc\Scramble\Infer\Flow;

class Edge
{
    public function __construct(
        public Node $from,
        public Node $to,
    ) {}
}
