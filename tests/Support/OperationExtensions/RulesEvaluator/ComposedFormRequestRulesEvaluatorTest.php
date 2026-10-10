<?php

namespace Dedoc\Scramble\Tests\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\ComposedFormRequestRulesEvaluator;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use PhpParser\PrettyPrinter;
use RuntimeException;

/** @param class-string<FormRequest> $requestClass */
function makeEvaluatorForRequest(string $requestClass, DiagnosticsCollector $diagnostics): ComposedFormRequestRulesEvaluator
{
    $routeInfo = new RouteInfo(new Route('POST', '/test/{myModel}', ['uses' => fn () => null]), 'POST');

    return new ComposedFormRequestRulesEvaluator(
        app(PrettyPrinter::class),
        ClassReflector::make($requestClass),
        'POST',
        $diagnostics->forClass($requestClass),
        $routeInfo,
    );
}

it('returns evaluated rules and reports only diagnostics from the successful evaluator', function (string $requestClass, array $expectedRules, array $expectedDiagnosticCodes) {
    $diagnostics = new DiagnosticsCollector;
    $evaluator = makeEvaluatorForRequest($requestClass, $diagnostics);

    expect($evaluator->handle())->toBe($expectedRules);
    expect($diagnostics->all()->map->code()->all())->toBe($expectedDiagnosticCodes);
})->with([
    [
        RulesForPostRequest::class,
        ['name' => ['required', 'string']],
        [],
    ],
    [
        NoRulesForPostRequest::class,
        [],
        [],
    ],
    [
        RequiresRouteModelRequest::class,
        ['name' => ['required', 'string']],
        [],
    ],
    [
        RequiresRouteModelWithoutRulesRequest::class,
        [],
        [],
    ],
    [
        MaximumFromRouteModelRequest::class,
        ['my_param' => ['int']],
        ['VR002'],
    ],
]);

class RulesForPostRequest extends FormRequest
{
    public function rules(): array
    {
        if (! $this->isMethod('POST')) {
            return [];
        }

        return ['name' => ['required', 'string']];
    }
}

class NoRulesForPostRequest extends FormRequest
{
    public function rules(): array
    {
        if (! $this->isMethod('POST')) {
            return ['name' => ['required', 'string']];
        }

        return [];
    }
}

class RequiresRouteModelRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->route('myModel') === null) {
            abort(404, 'My model not found. Unable to validate request.');
        }

        return ['name' => ['required', 'string']];
    }
}

class RequiresRouteModelWithoutRulesRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->route('myModel') === null) {
            abort(404, 'My model not found. Unable to validate request.');
        }

        return [];
    }
}

class MaximumFromRouteModelRequest extends FormRequest
{
    public function rules(): array
    {
        $myModel = $this->route('myModel');

        if ($myModel === null) {
            abort(404, 'My model not found. Unable to validate request.');
        }

        return ['my_param' => ['int', "max:{$myModel->value}"]];
    }
}

it('reports both warnings and throws the combined VR003 failure when fallback cannot recover', function () {
    $diagnostics = new DiagnosticsCollector;
    $evaluator = makeEvaluatorForRequest(UnreadableRulesRequest::class, $diagnostics);

    try {
        $evaluator->handle();
        $this->fail('Expected both rules evaluators to fail.');
    } catch (RulesEvaluationException $exception) {
        expect($exception->class)->toBe(UnreadableRulesRequest::class)
            ->and($exception->toDiagnostic()->code())->toBe('VR003');
    }

    expect($diagnostics->all()->map->code()->all())->toBe(['VR001', 'VR002']);
});

class UnreadableRulesRequest extends FormRequest
{
    public function rules(): array
    {
        // Direct evaluation fails at unavailableRules(). Node evaluation reports that
        // failure and continues, but cannot recover from the invalid arithmetic.
        return ['name' => [$this->unavailableRules(), 1 / 0]];
    }

    public function unavailableRules(): array
    {
        throw new RuntimeException('Rules are unavailable.');
    }
}
