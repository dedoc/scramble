<?php

namespace Dedoc\Scramble\Infer\Flow;

use Illuminate\Support\Str;
use PhpParser\PrettyPrinter;

class Edge
{
    public function __construct(
        public Node $from,
        public ?Node $to = null,
        public array $conditions = [],
        public bool $isNegated = false,
    )
    {
    }

    public function toDot(Nodes $nodes): string
    {
        if (! $this->to) {
            throw new \Exception('Incomplete edge, should not happen');
        }

        $phpParserExpressionPrinter = app(PrettyPrinter::class);

        $dot = $this->from->toDotId($nodes) . ' -> ' . $this->to->toDotId($nodes);

        $label = null;
        if ($this->conditions || $this->isNegated) {
            $label = implode(' AND ', array_map(
                fn ($expr) => $phpParserExpressionPrinter->prettyPrint([$expr]),
                $this->conditions,
            ));

            if ($this->isNegated) {
                $label = '!('.$label.')';
            }
        }

        if ($label) {
            $dot .= ' [label="'.Str::replace('"', '\"', $label).'"]';
        }

        return $dot;
    }
}
