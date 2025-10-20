<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\ContainerUtils;
use Dedoc\Scramble\Support\Generator\Combined\AllOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\DeepParametersMerger;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\QueryParametersConverter;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class RequestBodyExtension extends OperationExtension
{
    const HTTP_METHODS_WITHOUT_REQUEST_BODY = ['get', 'delete', 'head'];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $description = Str::of($routeInfo->phpDoc()->getAttribute('description')); // @phpstan-ignore argument.type

        /** @var Collection<int, ParametersExtractionResult> $rulesResults */
        $rulesResults = collect();

        try {
            $rulesResults = collect($this->extractParameters($operation, $routeInfo));
        } catch (Throwable $exception) {
            if (Scramble::shouldThrowOnError()) {
                throw $exception;
            }
            $description = $description->append('⚠️ Cannot generate request documentation: '.$exception->getMessage());
        }

        $operation
            ->summary(Str::of($routeInfo->phpDoc()->getAttribute('summary'))->rtrim('.'))  // @phpstan-ignore argument.type
            ->description($description);

        $allParams = $rulesResults->flatMap(fn ($p) => $p->parameters)->unique(fn ($p) => "$p->name.$p->in")->values()->all();

        if (empty($allParams)) {
            return;
        }

        if (in_array($operation->method, static::HTTP_METHODS_WITHOUT_REQUEST_BODY)) {
            $operation->addParameters($this->prepareQueryParams($allParams));

            return;
        }

        [$nonBodyParams, $bodyParams] = array_map(
            fn ($c) => $c->all(),
            collect($allParams)
                ->partition(fn (Parameter $p) => $p->in !== 'body' || $p->getAttribute('isInQuery') || $p->getAttribute('nonBody'))
                ->all(),
        );

        $operation->addParameters($this->prepareQueryParams($nonBodyParams));

        if (! $bodyParams) {
            return;
        }

        [$schemaResults, $schemalessResults] = $rulesResults->partition('schemaName')->all();
        $schemalessResults = collect([$this->mergeSchemalessRulesResults($schemalessResults->values())]);

        $schemas = $schemaResults->merge($schemalessResults)
            ->map(function (ParametersExtractionResult $r) use ($nonBodyParams) {
                $qpNames = collect($nonBodyParams)->keyBy(fn ($p) => "$p->name.$p->in");

                $r->parameters = collect($r->parameters)->filter(fn ($p) => ! $qpNames->has("$p->name.$p->in"))->values()->all();

                return $r;
            })
            ->filter(fn (ParametersExtractionResult $r) => count($r->parameters) || $r->schemaName)
            ->map($this->makeSchemaFromResults(...));

        if ($schemas->isEmpty()) {
            return;
        }

        $schema = $this->makeComposedRequestBodySchema($schemas);
        if (! $schema instanceof Reference) {
            $schema = Schema::fromType($schema);
        }

        $operation->addRequestBodyObject(
            RequestBodyObject::make()
                ->setContent($this->getMediaType($operation, $routeInfo, $allParams), $schema)
                ->required($this->isSchemaRequired($schema))
        );
    }

    protected function isSchemaRequired(Reference|Schema $schema): bool
    {
        $schema = $schema instanceof Reference
            ? $schema->resolve()
            : $schema;

        $type = $schema instanceof Schema ? $schema->type : $schema;

        if ($type instanceof ObjectType) {
            return count($type->required) > 0;
        }

        return false;
    }

    protected function makeSchemaFromResults(ParametersExtractionResult $result): Type
    {
        $requestBodySchema = Schema::createFromParameters(
            $parameters = $this->convertDotNamedParamsToComplexStructures($result->parameters)
        );

        if (count($parameters) === 1 && $parameters[0]->name === '*' && $parameters[0]->schema) {
            $requestBodySchema->type = $parameters[0]->schema->type;
        }

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

    /**
     * @param  Collection<int, Type>  $schemas
     */
    protected function makeComposedRequestBodySchema(Collection $schemas): Type
    {
        if ($schemas->count() === 1) {
            return $schemas->first(); // @phpstan-ignore return.type
        }

        return (new AllOf)->setItems($schemas->all());
    }

    /**
     * @param  Collection<int, ParametersExtractionResult>  $schemalessResults
     */
    protected function mergeSchemalessRulesResults(Collection $schemalessResults): ParametersExtractionResult
    {
        return new ParametersExtractionResult(
            parameters: $this->convertDotNamedParamsToComplexStructures($schemalessResults->values()->flatMap->parameters->unique(fn ($p) => "$p->name.$p->in")->values()->all()),
        );
    }

    /**
     * @param  Parameter[]  $params
     * @return Parameter[]
     */
    protected function prepareQueryParams(array $params): array
    {
        return config('scramble.flatten_deep_query_parameters', true)
            ? $this->convertDotNamedParamsToFlatQueryParams($params)
            : $this->convertDotNamedParamsToComplexStructures($params);
    }

    /**
     * @param  Parameter[]  $params
     * @return Parameter[]
     */
    protected function convertDotNamedParamsToComplexStructures(array $params): array
    {
        return (new DeepParametersMerger(collect($params)))->handle();
    }

    /**
     * @param  Parameter[]  $params
     * @return Parameter[]
     */
    protected function convertDotNamedParamsToFlatQueryParams(array $params): array
    {
        return (new QueryParametersConverter(collect($params)))->handle();
    }

    /**
     * @param  Parameter[]  $bodyParams
     */
    protected function getMediaType(Operation $operation, RouteInfo $routeInfo, array $bodyParams): string
    {
        if (
            ($mediaTags = $routeInfo->phpDoc()->getTagsByName('@requestMediaType'))
            && ($mediaType = trim(Arr::first($mediaTags)->value->value ?? null))
        ) {
            return $mediaType;
        }

        $jsonMediaType = 'application/json';

        if ($operation->method === 'get') {
            return $jsonMediaType;
        }

        return $this->hasBinary($bodyParams) ? 'multipart/form-data' : $jsonMediaType;
    }

    /**
     * @param  Parameter[]  $bodyParams
     */
    protected function hasBinary(array $bodyParams): bool
    {
        return collect($bodyParams)->contains(function (Parameter $parameter) {
            // @todo: Use OpenApi document tree walker when ready
            $parameterString = json_encode($parameter->toArray(), JSON_THROW_ON_ERROR);

            return Str::contains($parameterString, '"contentMediaType":"application\/octet-stream"');
        });
    }

    /**
     * @return ParametersExtractionResult[]
     */
    private function extractParameters(Operation $operation, RouteInfo $routeInfo): array
    {
        $result = [];
        foreach ($this->config->parametersExtractors->all() as $extractorClass) {
            /** @var ParameterExtractor $extractor */
            $extractor = ContainerUtils::makeContextable($extractorClass, [
                TypeTransformer::class => $this->openApiTransformer,
                Operation::class => $operation,
            ]);

            $result = $extractor->handle($routeInfo, $result);
        }

        return $result;
    }
}
