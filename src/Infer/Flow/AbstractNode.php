<?php

namespace Dedoc\Scramble\Infer\Flow;

use Illuminate\Support\Str;
use PhpParser\Node as PhpParserNode;
use PhpParser\PrettyPrinter;

abstract class AbstractNode implements Node
{
    public function definesVariable(string $varName): bool
    {
        return $this instanceof StatementNode
            && $this->parserNode instanceof \PhpParser\Node\Stmt\Expression
            && $this->parserNode->expr instanceof \PhpParser\Node\Expr\Assign
            && $this->parserNode->expr->var instanceof \PhpParser\Node\Expr\Variable
            && $this->parserNode->expr->var->name === $varName;
    }

    public function toDotId(Nodes $nodes): string
    {
        $index = array_search($this, $nodes->nodes, true);

        // @phpstan-ignore match.unhandled
        return match ($this::class) {
            StartNode::class => 'S',
            TerminateNode::class => match ($this->kind) {
                TerminationKind::RETURN => 'Ret',
                TerminationKind::THROW => 'Throw',
            },
            StatementNode::class => 'Stmt',
            MergeNode::class => 'M',
            ConditionNode::class => 'If',
        }.'_'.$index;
    }

    public function toDot(Nodes $nodes): string
    {
        $dot = $this->toDotId($nodes);

        $phpParserExpressionPrinter = app(PrettyPrinter::class);

        $empty = new \stdClass;

        $parserNode = $this->getParserNode();

        $label = match ($this::class) {
            TerminateNode::class => match ($this->kind) {
                TerminationKind::RETURN => 'Return',
                TerminationKind::THROW => 'Throw',
            }.($parserNode ? ' '.$phpParserExpressionPrinter->prettyPrint([$parserNode]) : ' VOID'),
            ConditionNode::class => 'If',
            StatementNode::class => $parserNode ? $phpParserExpressionPrinter->prettyPrint([$parserNode]) : '',
            default => $empty,
        };

        if ($label !== $empty) {
            $dot .= '[label="'.Str::replace('"', '\"', $label).'"]'; // @phpstan-ignore binaryOp.invalid, argument.type, argument.templateType
        }

        return $dot;
    }

    public function getParserNode(): ?PhpParserNode
    {
        return null;
    }
}
