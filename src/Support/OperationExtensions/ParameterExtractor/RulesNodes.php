<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\ConstFetchEvaluator;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class RulesNodes
{
    /**
     * @param Node\Expr\ArrayItem[] $nodes
     */
    public function __construct(
        public readonly array $nodes,
        public readonly ?string $className = null,
    )
    {
    }

    /**
     * @param Node[] $statements
     */
    public static function makeFromStatements(array $statements, ?string $className = null): self
    {
        return new self(
            nodes: (new NodeFinder)->find( // @phpstan-ignore argument.type
                $statements,
                fn (Node $node) => $node instanceof Node\Expr\ArrayItem && $node->getAttribute('parsedPhpDoc'),
            ),
            className: $className,
        );
    }

    /**
     * @return array<string, PhpDocNode>
     */
    public function getDocNodes(): array
    {
        return collect($this->nodes)
            ->mapWithKeys(function (Node\Expr\ArrayItem $item) {
                try {
                    $key = $this->buildEvaluator()->evaluateSilently($item->key);
                } catch (ConstExprEvaluationException $e) {
                    return [];
                }

                if (! is_string($key)) {
                    return [];
                }

                return [
                    $key => $item->getAttribute('parsedPhpDoc'),
                ];
            })
            ->toArray();
    }

    private function buildEvaluator(): ConstExprEvaluator
    {
        return (new ConstExprEvaluator(function ($expr) {
            if ($evaluatedConstFetch = (new ConstFetchEvaluator([
                'self' => $this->className,
                'static' => $this->className,
            ]))->evaluate($expr)) {
                return $evaluatedConstFetch;
            }

            return null;
        }));
    }

}
