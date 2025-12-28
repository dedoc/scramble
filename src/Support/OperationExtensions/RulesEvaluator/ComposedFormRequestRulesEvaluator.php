<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;

class ComposedFormRequestRulesEvaluator implements RulesEvaluator
{
    public function __construct(
        private PrettyPrinter $printer,
        private ClassReflector $classReflector,
        private string $method,
    ) {}

    public function handle(): array
    {
        $rulesMethodNode = $this->classReflector->getMethod('rules')->getAstNode();

        $returnNode = (new NodeFinder)->findFirst(
            $rulesMethodNode ? [$rulesMethodNode] : [],
            fn ($node) => $node instanceof Return_ && $node->expr instanceof Array_
        )?->expr ?? null;

        $evaluators = [
            new FormRequestRulesEvaluator($this->classReflector, $this->method),
            new NodeRulesEvaluator($this->printer, $rulesMethodNode, $returnNode, $this->method, $this->classReflector->className),
        ];

        foreach ($evaluators as $evaluator) {
            try {
                return $evaluator->handle();
            } catch (\Throwable $e) {
                // @todo communicate error
            }
        }

        return [];
    }
}
