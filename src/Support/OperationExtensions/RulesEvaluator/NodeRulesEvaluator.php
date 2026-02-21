<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Request;
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
use Throwable;

class NodeRulesEvaluator implements RulesEvaluator
{
    private ?Throwable $lastEvaluationException = null;

    public function __construct(
        private PrettyPrinter $printer,
        private FunctionLike $functionLikeNode,
        private ?Node\Expr $rulesNode,
        private string $method,
        private ?string $className,
        private Scope $scope,
    ) {}

    public function handle(): array
    {
        if (! $this->rulesNode) {
            return [];
        }

        try {
            return $this->rules();
        } catch (Throwable $e) {
            throw RulesEvaluationException::fromExceptions([
                self::class => $this->lastEvaluationException ?? $e,
            ]);
        }
    }

    /**
     * @return array<string, RuleSet>
     */
    private function rules(): array
    {
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

        return $rules; // @phpstan-ignore return.type
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
                } catch (Throwable $e) {
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
        $result = $this->doEvaluateExpression($expression, $variables);

        /*
         * If evaluation is successful, we reset the exception.
         *
         * Otherwise, this point won't be reached, and the specific evaluation exception will be used to
         * communicate the error.
         */
        $this->lastEvaluationException = null;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function doEvaluateExpression(?Node\Expr $expression, array $variables): mixed
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
            $request->setMethod(strtoupper($this->method));

            try {
                return eval("return $code;");
            } catch (Throwable $e) {
                $this->lastEvaluationException = $e;
            }

            /*
             * In case something happened while evaluating expression, we don't want to return just null.
             * It is important to preserve the base value type as much as possible in case the result of expression
             * evaluation gets passed to `array_merge`. So in case `$code` should've returned array, we return empty array.
             */
            $exprType = $this->getType($expr);

            return $exprType instanceof KeyedArrayType || $exprType instanceof ArrayType
                ? []
                : null;
        }))->evaluateDirectly($expression);
    }

    private function getType(Node\Expr $expr): Type
    {
        return ReferenceTypeResolver::getInstance()->resolve($this->scope, $this->scope->getType($expr));
    }
}
