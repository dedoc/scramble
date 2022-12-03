<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;

class FormRequestRulesExtractor
{
    private ?FunctionLike $handler;

    public function __construct(?FunctionLike $handler)
    {
        $this->handler = $handler;
    }

    public function shouldHandle()
    {
        if (! $this->handler) {
            return false;
        }

        return collect($this->handler->getParams())
            ->contains(\Closure::fromCallable([$this, 'findCustomRequestParam']));
    }

    public function node()
    {
    }

    public function extract(Route $route)
    {
        $requestParam = collect($this->handler->getParams())
            ->first(\Closure::fromCallable([$this, 'findCustomRequestParam']));

        $requestClassName = (string) $requestParam->type;

        /** @var Request $request */
        $request = (new $requestClassName);
        $request->setMethod($route->methods()[0]);

        return $request->rules();
    }

    private function findCustomRequestParam(Param $param)
    {
        $className = (string) $param->type;

        return method_exists($className, 'rules');
    }
}
