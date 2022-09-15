<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\FormRequestRulesExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameter;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ValidateCallExtractor;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use PhpParser\Node\Stmt\ClassMethod;
use Throwable;

class RequestBodyExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        $method = $operation->method;

        $description = Str::of($routeInfo->phpDoc()->getAttribute('description'));

        try {
            if (count($bodyParams = $this->extractParamsFromRequestValidationRules($routeInfo->route, $routeInfo->methodNode()))) {
                if ($method !== 'get') {
                    $operation->addRequestBodyObject(
                        RequestBodyObject::make()->setContent('application/json', Schema::createFromParameters($bodyParams))
                    );
                } else {
                    $operation->addParameters($bodyParams);
                }
            } elseif ($method !== 'get') {
                $operation
                    ->addRequestBodyObject(
                        RequestBodyObject::make()
                            ->setContent(
                                'application/json',
                                Schema::fromType(new ObjectType)
                            )
                    );
            }
        } catch (Throwable $exception) {
            $description = $description->append('⚠️Cannot generate request documentation: '.$exception->getMessage());
        }

        return $operation
            ->summary(Str::of($routeInfo->phpDoc()->getAttribute('summary'))->rtrim('.'))
            ->description($description);
    }

    /**
     * @throws Throwable
     */
    private function extractParamsFromRequestValidationRules(Route $route, ?ClassMethod $methodNode)
    {
        $rules = $this->extractRouteRequestValidationRules($route, $methodNode);

        if (! $rules) {
            return [];
        }

        return collect($rules)
            ->map(function ($rules, $name) {
                return (new RulesToParameter($name, $rules))->generate();
            })
            ->values()
            ->all();
    }

    /**
     * @throws Throwable
     */
    private function extractRouteRequestValidationRules(Route $route, $methodNode)
    {
        // Custom form request's class `validate` method
        if (($formRequestRulesExtractor = new FormRequestRulesExtractor($methodNode))->shouldHandle()) {
            if (count($rules = $formRequestRulesExtractor->extract($route))) {
                return $rules;
            }
        }

        if (($validateCallExtractor = new ValidateCallExtractor($methodNode))->shouldHandle()) {
            return $validateCallExtractor->extract($route);
        }

        return null;
    }
}
