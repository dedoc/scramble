<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Support\Generator\Operation;
use Illuminate\Http\Request;

class FormRequestRulesEvaluator implements RulesEvaluator
{
    public function __construct(
        private ClassReflector $classReflector,
        private Operation $operation
    ) {}

    public function handle(): array
    {
        return $this->rules($this->classReflector->className);
    }

    protected function rules(string $requestClassName)
    {
        /** @var Request $request */
        $request = (new $requestClassName);

        $rules = [];

        if (method_exists($request, 'setMethod')) {
            $request->setMethod(strtoupper($this->operation->method));
        }

        if (method_exists($request, 'rules')) {
            $rules = $request->rules();
        }

        return $rules;
    }
}
