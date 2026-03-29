<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Diagnostics\ValidationRules\Vr003AllEvaluatorsFailedDiagnostic;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
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
        private DiagnosticsCollector $diagnostics,
    ) {
        $this->diagnostics = $diagnostics->forContext('ComposedFormRequestRulesEvaluator');
    }

    public function handle(): array
    {
        $rulesMethod = $this->classReflector->getMethod('rules');
        $rulesMethodNode = $rulesMethod->getAstNode();

        /** @var Return_ $returnNodeStatement */
        $returnNodeStatement = (new NodeFinder)->findFirst(
            $rulesMethodNode ? [$rulesMethodNode] : [],
            fn ($node) => $node instanceof Return_ && $node->expr instanceof Array_
        );
        $returnNode = $returnNodeStatement?->expr ?? null;

        $evaluators = [
            new FormRequestRulesEvaluator($this->classReflector, $this->method, $this->diagnostics),
            new NodeRulesEvaluator($this->printer, $rulesMethodNode, $returnNode, $this->method, $this->classReflector->className, $rulesMethod->getFunctionLikeDefinition()->getScope(), $this->diagnostics),
        ];

        $exceptions = [];

        foreach ($evaluators as $evaluator) {
            try {
                return $evaluator->handle();
            } catch (\Throwable $e) {
                $exceptions[$evaluator::class] = $e;
            }
        }

        throw RulesEvaluationException::fromExceptions($exceptions)->forDiagnostics($this->diagnostics);
    }
}
