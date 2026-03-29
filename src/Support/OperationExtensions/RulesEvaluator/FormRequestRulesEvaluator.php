<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Diagnostics\ValidationRules\Vr001FormRequestRulesDiagnostic;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Illuminate\Http\Request;
use Throwable;

class FormRequestRulesEvaluator implements RulesEvaluator
{
    public function __construct(
        private ClassReflector $classReflector,
        private string $method,
        private DiagnosticsCollector $diagnostics,
    ) {
        $this->diagnostics = $diagnostics->forContext('FormRequestRulesEvaluator');
    }

    public function handle(): array
    {
        try {
            return $this->rules($this->classReflector->className, $this->method);
        } catch (Throwable $e) {
            $this->diagnostics->report(Vr001FormRequestRulesDiagnostic::fromThrowable($e));

            throw RulesEvaluationException::fromExceptions([self::class => $e])->forDiagnostics($this->diagnostics);
        }
    }

    protected function rules(string $requestClassName, string $method)
    {
        /** @var Request $request */
        $request = (new $requestClassName);

        $rules = [];

        if (method_exists($request, 'setMethod')) {
            $request->setMethod(strtoupper($method));
        }

        if (method_exists($request, 'rules')) {
            $rules = $request->rules();
        }

        return $rules;
    }
}
