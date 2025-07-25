<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Illuminate\Http\Request;
use Illuminate\Support\Optional;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\PrettyPrinter;
use stdClass;

class NodeRulesEvaluator implements RulesEvaluator
{
    public function __construct(
        private PrettyPrinter $printer,
        private FunctionLike $functionLikeNode,
        private ?Node $rulesNode,
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

        $rules = (new ConstExprEvaluator(function ($expr) use ($injectableParams) {
            $default = new stdClass;

            $evaluatedConstFetch = (new ConstFetchEvaluator([
                'self' => $this->className,
                'static' => $this->className,
            ]))->evaluate($expr, $default);

            if ($evaluatedConstFetch !== $default) {
                return $evaluatedConstFetch;
            }

            $code = $this->printer->prettyPrint([$expr]);

            extract($injectableParams);

            try {
                return eval("\$request = request(); return $code;");
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
}
