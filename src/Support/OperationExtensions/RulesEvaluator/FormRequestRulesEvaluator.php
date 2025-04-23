<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class FormRequestRulesEvaluator implements RulesEvaluator
{
    public function __construct(
        private ClassReflector $classReflector,
        private Route $route,
    ) {}

    public function handle(): array
    {
        return $this->rules($this->classReflector->className, $this->route);
    }

    protected function rules(string $requestClassName, Route $route)
    {
        /** @var Request $request */
        $request = (new $requestClassName);

        $rules = [];

        if (method_exists($request, 'setMethod')) {
            $request->setMethod($route->methods()[0]);
        }

        if (method_exists($request, 'rules')) {
            $rules = $request->rules();
        }

        return $rules;
    }
}
