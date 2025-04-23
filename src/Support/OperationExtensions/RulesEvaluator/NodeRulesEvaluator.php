<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Illuminate\Http\Request;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\PrettyPrinter;

class NodeRulesEvaluator implements RulesEvaluator
{
    public function __construct(
        private PrettyPrinter $printer,
        private FunctionLike $functionLikeNode,
        private ?Node $rulesNode,
    )
    {
    }
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
                    return [];
                }
            })
            ->all();

        $rules = (new ConstExprEvaluator(function ($expr) use ($injectableParams) {
            $code = $this->printer->prettyPrint([$expr]);

            extract($injectableParams);

            try {
                return eval("\$request = request(); return $code;");
            } catch (\Throwable $e) {
            }
        }))->evaluateSilently($this->rulesNode);

        foreach ($rules as $name => &$item) {
            $item = is_string($item) ? trim($item, '|') : array_values(array_filter($item));
        }

        return $rules ?? [];
    }
}
