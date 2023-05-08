<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Infer\Services\FileParser;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\NodeFinder;
use ReflectionClass;

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
        $requestClassName = $this->getFormRequestClassName();

        $result = resolve(FileParser::class)->parse((new ReflectionClass($requestClassName))->getFileName());

        /** @var Node\Stmt\ClassMethod|null $rulesMethodNode */
        $rulesMethodNode = $result->findMethod("$requestClassName@rules");

        if (! $rulesMethodNode) {
            return null;
        }

        return new ValidationNodesResult((new NodeFinder())->find(
            Arr::wrap($rulesMethodNode->stmts),
            fn (Node $node) => $node instanceof Node\Expr\ArrayItem
                && $node->key instanceof Node\Scalar\String_
                && $node->getAttribute('parsedPhpDoc')
        ));
    }

    public function extract(Route $route)
    {
        $requestClassName = $this->getFormRequestClassName();

        if ($requestClassName === 'Lorisleiva\Actions\ActionRequest') {
            $actionClassName = explode('@', $route->action['controller'])[0];
            $action = (new $actionClassName);
            return $action->rules();
        } else {
            /** @var Request $request */
            $request = (new $requestClassName);

            $request->setMethod($route->methods()[0]);

            return $request->rules();
        }
    }

    private function findCustomRequestParam(Param $param)
    {
        $className = (string) $param->type;

        return method_exists($className, 'rules');
    }

    private function getFormRequestClassName()
    {
        $requestParam = collect($this->handler->getParams())
            ->first(\Closure::fromCallable([$this, 'findCustomRequestParam']));

        return (string) $requestParam->type;
    }
}
