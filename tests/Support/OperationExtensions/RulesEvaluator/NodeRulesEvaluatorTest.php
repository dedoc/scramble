<?php

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Diagnostics\ValidationRules\Vr002NodeRulesEvaluationDiagnostic;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\NodeRulesEvaluator;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;

/**
 * @param  class-string|array{0: class-string, 1: string}  $action
 * @return array{0: array<string, mixed>, 1: DiagnosticsCollector}
 */
function evaluateNodeRules(string|array $action): array
{
    [$class, $method] = is_array($action) ? $action : [$action, '__invoke'];

    $routeInfo = new RouteInfo(new Route('POST', '/', ['uses' => "$class@$method"]), 'POST');
    $astNode = $routeInfo->actionNode();

    $call = (new NodeFinder)->findFirst(
        $astNode,
        fn (Node $node) => $node instanceof Node\Expr\MethodCall
            && $node->name instanceof Node\Identifier
            && $node->name->name === 'validate',
    );

    $rulesArg = $call instanceof Node\Expr\MethodCall
        && $call->var instanceof Node\Expr\Variable
        && $call->var->name === 'this'
        ? ($call->args[1] ?? null)
        : ($call->args[0] ?? null);

    $diagnostics = new DiagnosticsCollector;

    $rules = (new NodeRulesEvaluator(
        app(PrettyPrinter::class),
        $astNode,
        $rulesArg?->value,
        $routeInfo->method,
        $routeInfo->className(),
        $routeInfo->getScope(),
        $diagnostics,
        $routeInfo,
    ))->handle();

    return [$rules, $diagnostics];
}

it('evaluates validation rules from a controller method', function () {
    [$rules, $diagnostics] = evaluateNodeRules([Controller_NodeRulesEvaluatorTest::class, 'store']);

    expect($rules)->toBe([
        'name' => ['required', 'string'],
    ])->and($diagnostics->all())->toBeEmpty();
});

class Controller_NodeRulesEvaluatorTest
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string'],
        ]);
    }
}

it('reports VR002 when a non-Request parameter cannot be instantiated', function () {
    [$rules, $diagnostics] = evaluateNodeRules([ControllerWithModel_NodeRulesEvaluatorTest::class, 'store']);

    expect($rules)->toBe([
        'name' => ['required', 'string'],
    ])->and($diagnostics->all())->toBeEmpty();
});

class ControllerWithModel_NodeRulesEvaluatorTest
{
    public function store(Model $subject, Request $request)
    {
        $request->validate([
            'name' => ['required', 'string'],
        ]);
    }
}

it('reports VR002 when a non-Request parameter cannot be instantiated and used', function () {
    [$rules, $diagnostics] = evaluateNodeRules([ControllerWithModelUsed_NodeRulesEvaluatorTest::class, 'store']);

    expect($rules)->toBe([
        'name' => ['required', 'string'],
    ])->and($diagnostics->all())->toBeEmpty();
});
class ControllerWithModelUsed_NodeRulesEvaluatorTest
{
    public function store(Model $subject, Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', ...($subject->exists ? ['email'] : [])],
        ]);
    }
}

it('doesnt attempt to evaluate variables defined after rules', function () {
    [$rules, $diagnostics] = evaluateNodeRules([ControllerWithVarAfterRules_NodeRulesEvaluatorTest::class, 'store']);

    expect($rules)->toBe([
        'name' => ['required', 'string'],
    ])->and($diagnostics->all())->toBeEmpty();
});
class ControllerWithVarAfterRules_NodeRulesEvaluatorTest
{
    public function store(int $subject, Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', ...($subject ? [] : [])],
        ]);

        $subject = (fn () => throw new \Exception('Should not happen'))();
    }
}

it('evaluates variables defined before rules', function () {
    [$rules, $diagnostics] = evaluateNodeRules([ControllerWithVarBeforeRules_NodeRulesEvaluatorTest::class, 'store']);

    expect($rules)->toBe([
        'name' => ['required', 'string', 'email'],
    ])->and($diagnostics->all())->toBeEmpty();
});
class ControllerWithVarBeforeRules_NodeRulesEvaluatorTest
{
    public function store(Request $request)
    {
        $format = 'email';

        $request->validate([
            'name' => ['required', 'string', $format],
        ]);
    }
}

it('adds a tip when a validation rule expression fails', function () {
    [$rules, $diagnostics] = evaluateNodeRules([ControllerWithFailingRuleExpr_NodeRulesEvaluatorTest::class, 'store']);

    expect($rules)->toBe([
        'name' => ['string'],
    ])
        ->and($diagnostics->all()->sole())
        ->toBeInstanceOf(Vr002NodeRulesEvaluationDiagnostic::class)
        ->tip()->toBe(Vr002NodeRulesEvaluationDiagnostic::tipForExpression());
});
class ControllerWithFailingRuleExpr_NodeRulesEvaluatorTest
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => [$missing->foo, 'string'],
        ]);
    }
}

it('adds a tip when a variable assignment used by rules fails', function () {
    [$rules, $diagnostics] = evaluateNodeRules([ControllerWithFailingAssign_NodeRulesEvaluatorTest::class, 'store']);

    expect($diagnostics->all()->sole())
        ->toBeInstanceOf(Vr002NodeRulesEvaluationDiagnostic::class)
        ->tip()->toBe(Vr002NodeRulesEvaluationDiagnostic::tipForAssignment());
});
class ControllerWithFailingAssign_NodeRulesEvaluatorTest
{
    public function store(Request $request)
    {
        $format = $missing->foo;

        $request->validate([
            'name' => ['required', 'string', $format],
        ]);
    }
}
