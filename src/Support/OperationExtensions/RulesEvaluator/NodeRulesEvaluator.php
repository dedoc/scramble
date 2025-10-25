<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Optional;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;
use stdClass;

class NodeRulesEvaluator implements RulesEvaluator
{
    public function __construct(
        private PrettyPrinter $printer,
        private FunctionLike $functionLikeNode,
        private ?Node $rulesNode,
        private Route $route,
        private ?string $className,
    ) {}

    public function handle(): array
    {
        if (! $this->rulesNode) {
            return [];
        }

        $injectableParams = collect($this->functionLikeNode->getParams())
            ->filter(fn (Node\Param $param) => isset($param->type->name))
            ->filter(fn (Node\Param $param) => ! class_exists($className = (string) $param->type) || ! is_a($className, Request::class, true))
            ->filter(fn (Node\Param $param) => isset($param->var->name) && is_string($param->var->name))
            ->mapWithKeys(function (Node\Param $param) {
                try {
                    $type = (string) $param->type;
                    $primitives = [
                        'int' => 1,
                        'bool' => true,
                        'string' => '',
                        'float' => 1,
                    ];
                    $value = $primitives[$type] ?? app($type);

                    return [
                        $param->var->name => $value,
                    ];
                } catch (\Throwable $e) {
                    // @todo communicate warning
                    return [
                        $param->var->name => new Optional(null),
                    ];
                }
            })
            ->all();

        $variableNames = $this->collectVariableNames($injectableParams);
        $injectableVariables = $this->evaluateVariableAssigment($variableNames, $injectableParams);

        $rules = (new ConstExprEvaluator(function ($expr) use ($injectableParams, $injectableVariables) {
            $default = new stdClass;

            $evaluatedConstFetch = (new ConstFetchEvaluator([
                'self' => $this->className,
                'static' => $this->className,
            ]))->evaluate($expr, $default);

            if ($evaluatedConstFetch !== $default) {
                return $evaluatedConstFetch;
            }

            $code = $this->printer->prettyPrint([$expr]);

            extract($injectableVariables);
            extract($injectableParams);
            $request = request();

            if (method_exists($request, 'setMethod')) {
                $request->setMethod($this->route->methods()[0]);
            }

            try {
                return eval("return $code;");
            } catch (\Throwable $e) {
                // @todo communicate error
            }

            return null;
        }))->evaluateDirectly($this->rulesNode) ?? [];

        foreach ($rules as &$item) {
            if (is_string($item)) {
                $item = trim($item, '|,');

                continue;
            }

            if (is_array($item)) {
                $item = array_values(array_filter($item));
            }
        }

        return $rules;
    }

    private function collectVariableNames(array $excludeVariables = []): array
    {
        return collect((new NodeFinder)
            ->find($this->rulesNode, function ($n) {
                return $n instanceof Variable && is_string($n->name);
            }))
            ->filter(fn (Variable $variable) => ! array_key_exists($variable->name, $excludeVariables))
            ->unique(fn (Variable $variable) => $variable->name)
            ->map(fn (Variable $variable) => $variable->name)
            ->all();
    }

    private function evaluateVariableAssigment(array $variables, array $predefinedVariables = []): array
    {
        if (empty($variables)) {
            return [];
        }

        return collect($this->functionLikeNode->getStmts())
            ->filter(fn (Stmt $stmt) => $stmt->expr instanceof Assign)
            ->filter(fn (Stmt $stmt) => isset($stmt->expr->var->name) && in_array($stmt->expr->var->name, $variables))
            ->flatMap(function (Stmt $stmt) use ($predefinedVariables) {
                try {
                    $code = $this->printer->prettyPrint([$stmt->expr]);
                    extract($predefinedVariables);
                    $request = request();

                    if (method_exists($request, 'setMethod')) {
                        $request->setMethod($this->route->methods()[0]);
                    }

                    return [$stmt->expr->var->name => eval("return $code;")];
                } catch (\Throwable $e) {
                    // @todo communicate error
                }
            })
            ->all();
    }
}
