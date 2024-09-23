<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\FormRequestRulesExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RequestMethodCallsExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ValidateCallExtractor;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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

        $rulesResults = collect();

        try {
            $rulesResults = collect($this->extractRouteRequestValidationRules($routeInfo, $routeInfo->methodNode()));
        } catch (Throwable $exception) {
            if (app()->environment('testing')) {
                throw $exception;
            }
            $description = $description->append('⚠️Cannot generate request documentation: '.$exception->getMessage());
        }

        $operation
            ->summary(Str::of($routeInfo->phpDoc()->getAttribute('summary'))->rtrim('.'))
            ->description($description);

        $allParams = $rulesResults->flatMap->parameters->values()->all();

        [$queryParams, $bodyParams] = collect($allParams)
            ->partition(fn (Parameter $p) => $p->getAttribute('isInQuery'))
            ->map->toArray();

        $mediaType = $this->getMediaType($operation, $routeInfo, $allParams);

        if (empty($allParams)) {
            return;
        }

        $operation->addParameters($queryParams);
        if (in_array($operation->method, static::HTTP_METHODS_WITHOUT_REQUEST_BODY)) {
            $operation->addParameters($bodyParams);

            return;
        }

        [$schemaResults, $schemalessResults] = $rulesResults->partition('schemaName');
        $schemalessResults = collect([$this->mergeSchemalessRulesResults($schemalessResults->values())]);

        $schemas = $schemaResults->merge($schemalessResults)
            ->filter(fn (ParametersExtractionResult $r) => count($r->parameters) || $r->schemaName)
            ->map(function (ParametersExtractionResult $r) use ($queryParams) {
                $qpNames = collect($queryParams)->keyBy('name');

                $r->parameters = collect($r->parameters)->filter(fn ($p) => !$qpNames->has($p->name))->values()->all();

                return $r;
            })
            ->values()
            ->map($this->makeSchemaFromResults(...));

        if ($schemas->isEmpty()) {
            return;
        }

        $schema = $this->makeComposedRequestBodySchema($schemas);
        if (! $schema instanceof Reference) {
            $schema = Schema::fromType($schema);
        }

        $operation->addRequestBodyObject(
            RequestBodyObject::make()->setContent($mediaType, $schema),
        );
    }

    protected function makeSchemaFromResults(ParametersExtractionResult $result): Type
    {
        $requestBodySchema = Schema::createFromParameters($result->parameters);

        if (! $result->schemaName) {
            return $requestBodySchema->type;
        }

        $components = $this->openApiTransformer->getComponents();
        if (! $components->hasSchema($result->schemaName)) {
            $requestBodySchema->type->setDescription($result->description ?: '');

            $components->addSchema($result->schemaName, $requestBodySchema);
        }

        return new Reference('schemas', $result->schemaName, $components);
    }

    protected function makeComposedRequestBodySchema(Collection $schemas)
    {
        if ($schemas->count() === 1) {
            return $schemas->first();
        }

        return (new AllOf())->setItems($schemas->all());
    }

    protected function mergeSchemalessRulesResults(Collection $schemalessResults): ParametersExtractionResult
    {
        return new ParametersExtractionResult(
            parameters: $schemalessResults->values()->flatMap->parameters->values()->all(),
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

    protected function extractRouteRequestValidationRules(RouteInfo $routeInfo, $methodNode)
    {
        $handlers = [
            new FormRequestRulesExtractor($methodNode, $this->openApiTransformer),
            new RequestMethodCallsExtractor(),
            new ValidateCallExtractor($methodNode, $this->openApiTransformer),
        ];

        return collect($handlers)
            ->filter(fn ($h) => $h->shouldHandle())
            ->map(fn ($h) => $h->extract($routeInfo))
            ->values()
            ->toArray();
    }
}
