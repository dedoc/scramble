<?php

namespace Dedoc\Scramble\Infer\Flow;

use Illuminate\Support\Str;
use PhpParser\PrettyPrinter;

abstract class AbstractNode implements Node
{
    public function toDotId(Nodes $nodes): string
    {
        $index = array_search($this, $nodes->nodes, true);

        return match ($this::class) {
            StartNode::class => 'S',
            TerminateNode::class => match ($this->type) {
                TerminationType::RETURN => 'Ret',
                TerminationType::THROW => 'Throw',
            },
            UnknownNode::class => 'Unk',
            MergeNode::class => 'M',
            ConditionNode::class => 'If',
        }.'_'.$index;
    }

    public function toDot(Nodes $nodes): ?string
    {
        $dot = $this->toDotId($nodes);

        $phpParserExpressionPrinter = app(PrettyPrinter::class);

        $empty = new \stdClass;

        $label = match ($this::class) {
            TerminateNode::class => match ($this->type) {
                TerminationType::RETURN => 'Return',
                TerminationType::THROW => 'Throw',
            }.($this->value ? ' '.$phpParserExpressionPrinter->prettyPrintExpr($this->value) : ' VOID'),
            ConditionNode::class => 'If',
            UnknownNode::class => $phpParserExpressionPrinter->prettyPrint([$this->parserNode]),
            default => $empty,
        };

        if ($label !== $empty) {
            $dot .= '[label="'.Str::replace('"', '\"', $label).'"]';
        }

        return $dot;
    }
}
