<?php

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Diagnostics\ValidationRules\Vr002NodeRulesEvaluationDiagnostic;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\NodeRulesEvaluator;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\RuleType;
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

it('recovers a concrete intermediate string before concatenating it', function () {
    [$rules] = evaluateNodeRules([ControllerWithRecovery_NodeRulesEvaluatorTest::class, 'format']);

    expect($rules)->toBe(['date' => ['date_format:Y|m']]);
});

it('does not use an unknown boolean as a truthy PHP object', function () {
    [$rules] = evaluateNodeRules([ControllerWithRecovery_NodeRulesEvaluatorTest::class, 'condition']);

    expect($rules)->toBe(['rate' => ['nullable', 'numeric']]);
});

it('extracts indexed rules from an unavailable intermediate array', function () {
    [$rules] = evaluateNodeRules([ControllerWithRecovery_NodeRulesEvaluatorTest::class, 'indexed']);

    expect($rules)->toBe(['email' => ['required', 'email']]);
});

it('extracts an unavailable root rules map without iterating a symbolic object', function () {
    [$rules] = evaluateNodeRules([ControllerWithRecovery_NodeRulesEvaluatorTest::class, 'root']);

    expect($rules['email'])->toBe(['required', 'email'])
        ->and($rules['document'][0])->toBeInstanceOf(RuleType::class)
        ->and($rules['document'][0]->type->toString())->toBe(RecoveryRule_NodeRulesEvaluatorTest::class);
});

it('recovers a direct rules map spread and preserves concrete sibling rules', function () {
    [$rules] = evaluateNodeRules([ControllerWithRecovery_NodeRulesEvaluatorTest::class, 'spread']);

    expect($rules['email'])->toBe(['required', 'email'])
        ->and($rules['document'][0])->toBeInstanceOf(RuleType::class)
        ->and($rules['sibling'][0])->toBeInstanceOf(RecoveryRule_NodeRulesEvaluatorTest::class);
});

it('recovers a failed field without making sibling fields static', function (string $method) {
    [$rules] = evaluateNodeRules([ControllerWithRecovery_NodeRulesEvaluatorTest::class, $method]);

    expect($rules['rate'])->toBe(['nullable', 'numeric'])
        ->and($rules['sibling'][0])->toBeInstanceOf(RecoveryRule_NodeRulesEvaluatorTest::class);
})->with(['fields', 'fieldsWithIntermediateValue']);

it('does not pass symbolic fragments into an enclosing PHP method call', function () {
    ControllerWithRecovery_NodeRulesEvaluatorTest::$transformCalls = 0;

    [$rules] = evaluateNodeRules([ControllerWithRecovery_NodeRulesEvaluatorTest::class, 'transformed']);

    expect($rules['email'])->toBe(['required', 'email'])
        ->and(ControllerWithRecovery_NodeRulesEvaluatorTest::$transformCalls)->toBe(0);
});

it('rejects a non-array root value without exposing its properties as rules', function () {
    [$rules, $diagnostics] = evaluateNodeRules([ControllerWithRecovery_NodeRulesEvaluatorTest::class, 'invalidRoot']);

    expect($rules)->toBe([])
        ->and($diagnostics->all())->not->toBeEmpty();
});

class RecoveryRule_NodeRulesEvaluatorTest {}

class ControllerWithRecovery_NodeRulesEvaluatorTest
{
    public static int $transformCalls = 0;

    public function format(Request $request)
    {
        $format = $this->dateFormat();

        $request->validate(['date' => ['date_format:'.$format]]);
    }

    public function condition(Request $request)
    {
        $condition = $this->unknownBoolean();

        $request->validate(['rate' => [$condition ? 'required' : 'nullable', 'numeric']]);
    }

    public function indexed(Request $request)
    {
        $rules = $this->documentRules();

        $request->validate(['email' => $rules['email']]);
    }

    public function root(Request $request)
    {
        $rules = $this->documentRules();

        $request->validate($rules);
    }

    public function spread(Request $request)
    {
        $request->validate([
            ...$this->documentRules(),
            'sibling' => [new RecoveryRule_NodeRulesEvaluatorTest],
        ]);
    }

    public function transformed(Request $request)
    {
        $request->validate([
            ...$this->transform($this->documentRules()),
        ]);
    }

    public function fields(Request $request)
    {
        $request->validate([
            'rate' => [$this->unknownBoolean() ? 'required' : 'nullable', 'numeric'],
            'sibling' => [new RecoveryRule_NodeRulesEvaluatorTest],
        ]);
    }

    public function fieldsWithIntermediateValue(Request $request)
    {
        $condition = $this->unknownBoolean();

        $request->validate([
            'rate' => [$condition ? 'required' : 'nullable', 'numeric'],
            'sibling' => [new RecoveryRule_NodeRulesEvaluatorTest],
        ]);
    }

    public function invalidRoot(Request $request)
    {
        $request->validate($this->unknownObject());
    }

    private function dateFormat(): string
    {
        app('scramble.unbound-recovery-service');

        return 'Y|m';
    }

    private function unknownBoolean(): bool
    {
        return app('scramble.unbound-recovery-service')->enabled();
    }

    private function unknownObject(): object
    {
        return app('scramble.unbound-recovery-service')->settings();
    }

    private function documentRules(): array
    {
        app('scramble.unbound-recovery-service');

        return [
            'email' => ['required', 'email'],
            'document' => [new RecoveryRule_NodeRulesEvaluatorTest],
        ];
    }

    private function transform(array $rules): array
    {
        self::$transformCalls++;

        return $rules;
    }
}
