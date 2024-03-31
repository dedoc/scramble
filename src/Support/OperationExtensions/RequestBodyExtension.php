<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\FormRequestRulesExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ValidateCallExtractor;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PhpParser\Node\Stmt\ClassMethod;
use Throwable;

class RequestBodyExtension extends OperationExtension
{
    const HTTP_METHODS_WITHOUT_REQUEST_BODY = ['get', 'delete', 'head'];

    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        $description = Str::of($routeInfo->phpDoc()->getAttribute('description'));

        try {
            $bodyParams = $this->extractParamsFromRequestValidationRules($routeInfo->route, $routeInfo->methodNode(), $routeInfo);

            $mediaType = $this->getMediaType($operation, $routeInfo, $bodyParams);

            if (count($bodyParams)) {
                if (! in_array($operation->method, static::HTTP_METHODS_WITHOUT_REQUEST_BODY)) {
                    $operation->addRequestBodyObject(
                        RequestBodyObject::make()->setContent($mediaType, Schema::createFromParameters($bodyParams))
                    );
                } else {
                    $operation->addParameters($bodyParams);
                }
            } elseif (! in_array($operation->method, static::HTTP_METHODS_WITHOUT_REQUEST_BODY)) {
                $operation
                    ->addRequestBodyObject(
                        RequestBodyObject::make()
                            ->setContent(
                                $mediaType,
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

    protected function getMediaType(Operation $operation, RouteInfo $routeInfo, array $bodyParams): string
    {
        if (
            ($mediaTags = $routeInfo->phpDoc()->getTagsByName('@requestMediaType'))
            && ($mediaType = trim(Arr::first($mediaTags)?->value?->value))
        ) {
            return $mediaType;
        }

        $jsonMediaType = 'application/json';

        if ($operation->method === 'get') {
            return $jsonMediaType;
        }

        return $this->hasBinary($bodyParams) ? 'multipart/form-data' : $jsonMediaType;
    }

    protected function hasBinary($bodyParams): bool
    {
        return collect($bodyParams)->contains(function (Parameter $parameter) {
            if (property_exists($parameter?->schema?->type, 'format')) {
                return $parameter->schema->type->format === 'binary';
            }

            return false;
        });
    }

    protected function extractParamsFromRequestValidationRules(Route $route, ?ClassMethod $methodNode, $routeInfo)
    {
        [$rules, $nodesResults] = $this->extractRouteRequestValidationRules($route, $methodNode, $routeInfo);

        return (new RulesToParameters($rules, $nodesResults, $this->openApiTransformer))->handle();
    }

    protected function extractRouteRequestValidationRules(Route $route, $methodNode, $routeInfo)
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
            if ($validateCallRules = $validateCallExtractor->extract($routeInfo)) {
                $rules = array_merge($rules, $validateCallRules);
                $nodesResults[] = $validateCallExtractor->node();
            }
        }

        return [$rules, array_filter($nodesResults)];
    }
}
