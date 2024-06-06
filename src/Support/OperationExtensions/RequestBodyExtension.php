<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
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

        /*
         * Making sure to analyze the route.
         * @todo rename the method
         */
        $routeInfo->getMethodType();

        [$bodyParams, $schemaName, $schemaDescription] = [[], null, null];
        try {
            [$bodyParams, $schemaName, $schemaDescription] = $this->extractParamsFromRequestValidationRules($routeInfo->route, $routeInfo->methodNode());
        } catch (Throwable $exception) {
            if (app()->environment('testing')) {
                throw $exception;
            }
            $description = $description->append('⚠️Cannot generate request documentation: '.$exception->getMessage());
        }

        $operation
            ->summary(Str::of($routeInfo->phpDoc()->getAttribute('summary'))->rtrim('.'))
            ->description($description);

        $bodyParamsNames = array_map(fn ($p) => $p->name, $bodyParams);

        $allParams = [
            ...$bodyParams,
            ...array_filter(
                array_values($routeInfo->requestParametersFromCalls->data),
                fn ($p) => ! in_array($p->name, $bodyParamsNames),
            ),
        ];
        [$queryParams, $bodyParams] = collect($allParams)
            ->partition(function (Parameter $parameter) {
                return $parameter->getAttribute('isInQuery');
            });
        $queryParams = $queryParams->toArray();
        $bodyParams = $bodyParams->toArray();

        $mediaType = $this->getMediaType($operation, $routeInfo, $allParams);

        if (empty($allParams)) {
            if (! in_array($operation->method, static::HTTP_METHODS_WITHOUT_REQUEST_BODY)) {
                $operation
                    ->addRequestBodyObject(
                        RequestBodyObject::make()->setContent($mediaType, Schema::fromType(new ObjectType))
                    );
            }

            return;
        }

        $operation->addParameters($queryParams);
        if (in_array($operation->method, static::HTTP_METHODS_WITHOUT_REQUEST_BODY)) {
            $operation->addParameters($bodyParams);

            return;
        }

        $this->addRequestBody(
            $operation,
            $mediaType,
            Schema::createFromParameters($bodyParams),
            $schemaName,
            $schemaDescription,
        );
    }

    protected function addRequestBody(Operation $operation, string $mediaType, Schema $requestBodySchema, ?string $schemaName, ?string $schemaDescription)
    {
        if (! $schemaName) {
            $operation->addRequestBodyObject(RequestBodyObject::make()->setContent($mediaType, $requestBodySchema));

            return;
        }

        $components = $this->openApiTransformer->getComponents();
        if (! $components->hasSchema($schemaName)) {
            $requestBodySchema->type->setDescription($schemaDescription ?: '');

            $components->addSchema($schemaName, $requestBodySchema);
        }

        $operation->addRequestBodyObject(
            RequestBodyObject::make()->setContent(
                $mediaType,
                new Reference('schemas', $schemaName, $components),
            )
        );
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
            // @todo: Use OpenApi document tree walker when ready
            $parameterString = json_encode($parameter->toArray());

            return Str::contains($parameterString, '"format":"binary"');
        });
    }

    protected function extractParamsFromRequestValidationRules(Route $route, ?ClassMethod $methodNode)
    {
        [$rules, $nodesResults] = $this->extractRouteRequestValidationRules($route, $methodNode);

        return [
            (new RulesToParameters($rules, $nodesResults, $this->openApiTransformer))->handle(),
            $nodesResults[0]->schemaName ?? null,
            $nodesResults[0]->description ?? null,
        ];
    }

    protected function extractRouteRequestValidationRules(Route $route, $methodNode)
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
