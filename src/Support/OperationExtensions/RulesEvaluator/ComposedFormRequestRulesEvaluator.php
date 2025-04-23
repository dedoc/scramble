<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Illuminate\Routing\Route;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;

class ComposedFormRequestRulesEvaluator implements RulesEvaluator
{
    public function __construct(
        private PrettyPrinter $printer,
        private ClassReflector $classReflector,
        private Route $route,
    )
    {
    }

    public function handle(): array
    {
        $rulesMethodNode = $this->classReflector->getMethod('rules')->getAstNode();

        $returnNode = (new NodeFinder())->findFirst(
            $rulesMethodNode ? [$rulesMethodNode] : [],
            fn ($node) => $node instanceof Return_ && $node->expr instanceof Array_
        )?->expr ?? null;

        $evaluators = [
            new FormRequestRulesEvaluator($this->classReflector, $this->route),
            new NodeRulesEvaluator($this->printer, $rulesMethodNode, $returnNode)
        ];

        $exceptions = [];
        foreach ($evaluators as $evaluator) {
            try {
                return $evaluator->handle();
            } catch (\Throwable $e) {
                $exceptions[] = $e;
                // todo: reporting
            }
        }

        throw $exceptions[count($exceptions) - 1] ?? new \RuntimeException('No rules evaluator could be used to extract rules from the request.');
    }
}
