<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Support\SchemaClassDocReflector;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\NodeFinder;
use ReflectionClass;
use Spatie\LaravelData\Contracts\BaseData;

class FormRequestRulesExtractor
{
    private ?FunctionLike $handler;

    public function __construct(?FunctionLike $handler)
    {
        $this->handler = $handler;
    }

    public function shouldHandle(): bool
    {
        if (! $this->handler) {
            return false;
        }

        if (! collect($this->handler->getParams())->contains($this->findCustomRequestParam(...))) {
            return false;
        }

        return true;
    }

    /**
     * @return ValidationNodesResult[]
     */
    public function nodes(): array
    {
        return $this->getFormRequestClassNames()->map(function (string $requestClassName) {
            $classReflector = Infer\Reflector\ClassReflector::make($requestClassName);

            $phpDocReflector = SchemaClassDocReflector::createFromDocString($classReflector->getReflection()->getDocComment() ?: '');

            $schemaName = ($phpDocReflector->getTagValue('@ignoreSchema')->value ?? null) !== null
                ? null
                : $phpDocReflector->getSchemaName($requestClassName);

            return new ValidationNodesResult(
                (new NodeFinder)->find(
                    Arr::wrap($classReflector->getMethod('rules')->getAstNode()->stmts),
                    fn (Node $node) => $node instanceof Node\Expr\ArrayItem
                        && $node->key instanceof Node\Scalar\String_
                        && $node->getAttribute('parsedPhpDoc'),
                ),
                schemaName: $schemaName,
                description: $phpDocReflector->getDescription(),
            );
        })->all();
    }

    public function extract(Route $route): array
    {
        $rules = [];

        $this->getFormRequestClassNames()->each(function (string $requestClassName) use ($route, &$rules) {
            /** @var Request $request */
            $request = (new $requestClassName);

            if (method_exists($request, 'setMethod')) {
                $request->setMethod($route->methods()[0]);
            }

            if (method_exists($request, 'rules')) {
                $rules = array_merge($rules, $request->rules());
            }
        });

        return $rules;
    }

    private function findCustomRequestParam(Param $param): bool
    {
        if (! $param->type || ! method_exists($param->type, '__toString')) {
            return false;
        }

        $className = (string) $param->type;

        if (is_a($className, BaseData::class, true)) {
            return false;
        }

        return method_exists($className, 'rules');
    }

    private function getFormRequestClassNames(): Collection
    {
        return collect($this->handler->getParams())
            ->filter($this->findCustomRequestParam(...))
            ->map(function($requestParam){
                $requestClassName = (string) $requestParam->type;

                $reflectionClass = new ReflectionClass($requestClassName);

                // If the classname is actually an interface, it might be bound to the container.
                if (! $reflectionClass->isInstantiable() && app()->bound($requestClassName)) {
                    $classInstance = app()->getBindings()[$requestClassName]['concrete'](app());
                    $requestClassName = $classInstance::class;
                }

                return $requestClassName;
            });
    }
}
