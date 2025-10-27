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
        private ?Node\Expr $rulesNode,
        private Route $route,
        private ?string $className,
    ) {}

    public function handle(): array
    {
        if (! $this->rulesNode) {
            return [];
        }

        $vars = $this->evaluateDefinedVars();

        $rules = $this->evaluateExpression($this->rulesNode, $vars) ?? [];

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

    /**
     * @return array<string, mixed>
     */
    private function evaluateDefinedVars(): array
    {
        $parameters = $this->evaluateParameters();

        $variables = $this->evaluateVariables($parameters);

        return array_merge($variables, $parameters);
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluateParameters(): array
    {
        return collect($this->functionLikeNode->getParams())
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
    }

    /**
     * @param  array<string, mixed>  $predefinedVariables
     * @return array<string, mixed>
     */
    private function evaluateVariables(array $predefinedVariables = []): array
    {
        $variables = $this->collectVariableNames();

        if (empty($variables)) {
            return [];
        }

        return collect($this->functionLikeNode->getStmts())
            ->filter(fn (Stmt $stmt) => $stmt instanceof Stmt\Expression && $stmt->expr instanceof Assign)
            ->filter(fn (Stmt $stmt) => isset($stmt->expr->var->name) && in_array($stmt->expr->var->name, $variables))
            ->reduce(fn (array $variables, Stmt $stmt) => [
                ...$variables,
                $stmt->expr->var->name => $this->evaluateExpression($stmt->expr, array_merge($variables, $predefinedVariables)), // @phpstan-ignore property.notFound
            ], []);
    }

    /**
     * @return string[]
     */
    private function collectVariableNames(): array
    {
        return array_values(array_filter(array_map(
            fn (Variable $var) => is_string($var->name) ? $var->name : null,
            (new NodeFinder)->findInstanceOf($this->rulesNode ?: [], Variable::class),
        )));
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function evaluateExpression(?Node\Expr $expression, array $variables): mixed
    {
        if (! $expression) {
            return null;
        }

        return (new ConstExprEvaluator(function ($expr) use ($variables) {
            $default = new stdClass;

            $evaluatedConstFetch = (new ConstFetchEvaluator([
                'self' => $this->className,
                'static' => $this->className,
            ]))->evaluate($expr, $default);

            if ($evaluatedConstFetch !== $default) {
                return $evaluatedConstFetch;
            }

            $code = $this->printer->prettyPrint([$expr]);

            extract($variables);
            $request = request();
            $request->setMethod($this->route->methods()[0]);

            try {
                return eval("return $code;");
            } catch (\Throwable $e) {
                // @todo communicate error
            }

            return null;
        }))->evaluateDirectly($expression);
    }
}
