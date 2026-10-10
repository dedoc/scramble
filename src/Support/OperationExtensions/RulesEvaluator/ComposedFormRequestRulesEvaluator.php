<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Support\RouteInfo;
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
        private RouteInfo $routeInfo,
    ) {}

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

        $formRequestDiagnostics = new DiagnosticsCollector;
        $nodeDiagnostics = new DiagnosticsCollector;

        $evaluators = [
            [new FormRequestRulesEvaluator($this->classReflector, $this->method, $formRequestDiagnostics), $formRequestDiagnostics],
            [new NodeRulesEvaluator($this->printer, $rulesMethodNode, $returnNode, $this->method, $this->classReflector->className, $rulesMethod->getFunctionLikeDefinition()->getScope(), $nodeDiagnostics, $this->routeInfo), $nodeDiagnostics],
        ];

        $exceptions = [];

        foreach ($evaluators as [$evaluator, $diagnostics]) {
            try {
                $rules = $evaluator->handle();
            } catch (\Throwable $e) {
                $exceptions[$evaluator::class] = $e;

                continue;
            }

            foreach ($diagnostics->all() as $diagnostic) {
                $this->diagnostics->reportOnce($diagnostic);
            }

            return $rules;
        }

        foreach ($evaluators as [, $diagnostics]) {
            foreach ($diagnostics->all() as $diagnostic) {
                $this->diagnostics->reportOnce($diagnostic);
            }
        }

        throw RulesEvaluationException::fromExceptions($exceptions)
            ->forClass($this->classReflector->className);
    }
}
