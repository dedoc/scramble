<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\FormRequestRulesExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
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
            if (app()->environment('testing')) {
                throw $exception;
            }
            $description = $description->append('⚠️Cannot generate request documentation: '.$exception->getMessage());
        }

        $operation
            ->summary(Str::of($routeInfo->phpDoc()->getAttribute('summary'))->rtrim('.'))
            ->description($description);
    }

    private function extractParamsFromRequestValidationRules(Route $route, ?ClassMethod $methodNode)
    {
        [$rules, $nodesResults] = $this->extractRouteRequestValidationRules($route, $methodNode);

        return (new RulesToParameters($rules, $nodesResults, $this->openApiTransformer))->handle();
    }

    private function extractRouteRequestValidationRules(Route $route, $methodNode)
    {
        $rules = [];
        $nodesResults = [];

        // Custom form request's class `validate` method
        if (($formRequestRulesExtractor = new FormRequestRulesExtractor($methodNode))->shouldHandle()) {
            if (count($formRequestRules = $formRequestRulesExtractor->extract($route))) {
                $rules = array_merge($rules, $formRequestRules);
                $nodesResults[] = $formRequestRulesExtractor->node();
            }
        }

        if (($validateCallExtractor = new ValidateCallExtractor($methodNode))->shouldHandle()) {
            if ($validateCallRules = $validateCallExtractor->extract()) {
                $rules = array_merge($rules, $validateCallRules);
                $nodesResults[] = $validateCallExtractor->node();
            }
        }

        return [$rules, array_filter($nodesResults)];
    }
}
