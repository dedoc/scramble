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
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\DeepParametersMerger;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
         * @todo if this methods returns null, make sure to notify users about this.
         */
        $routeInfo->getMethodType();

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
            ->summary(Str::of($routeInfo->phpDoc()->getAttribute('summary'))->rtrim('.'))
            ->description($description);

        $allParams = $rulesResults->flatMap->parameters->unique(fn ($p) => "$p->name.$p->in")->values()->all();

        $mediaType = $this->getMediaType($operation, $routeInfo, $allParams);

        if (empty($allParams)) {
            return;
        }

        if (in_array($operation->method, static::HTTP_METHODS_WITHOUT_REQUEST_BODY)) {
            $operation->addParameters(
                $this->convertDotNamedParamsToQueryParams($allParams)
            );

            return;
        }

        [$nonBodyParams, $bodyParams] = collect($allParams)
            ->partition(fn (Parameter $p) => $p->in !== 'body' || $p->getAttribute('isInQuery') || $p->getAttribute('nonBody'))
            ->map->toArray();

        $operation->addParameters(
            $this->convertDotNamedParamsToQueryParams($nonBodyParams)
        );

        if (! $bodyParams) {
            return;
        }

        [$schemaResults, $schemalessResults] = $rulesResults->partition('schemaName');
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
                ->setContent($mediaType, $schema)
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

        if (count($parameters) === 1 && $parameters[0]?->name === '*') {
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

    protected function makeComposedRequestBodySchema(Collection $schemas)
    {
        if ($schemas->count() === 1) {
            return $schemas->first();
        }

        return (new AllOf)->setItems($schemas->all());
    }

    protected function mergeSchemalessRulesResults(Collection $schemalessResults): ParametersExtractionResult
    {
        return new ParametersExtractionResult(
            parameters: $this->convertDotNamedParamsToComplexStructures($schemalessResults->values()->flatMap->parameters->unique(fn ($p) => "$p->name.$p->in")->values()->all()),
        );
    }

    protected function convertDotNamedParamsToComplexStructures($params)
    {
        return (new DeepParametersMerger(collect($params)))->handle();
    }

    /**
     * @param  Parameter[]  $params
     * @return Parameter[]
     */
    protected function convertDotNamedParamsToQueryParams(array $params): array
    {
        /** @var Collection<string, Parameter> $paramsByName */
        $paramsByName = collect($params)->keyBy->name;

        [$convertableParameters, $deepParameters] = collect($params)
            /*
             * Rejecting array "container" parameters for cases when there are properties specified. For example:
             * ['filter' => 'array', 'filter.accountable' => 'integer']
             * In this ruleset `filter` should not be documented at all as the accountable is enough.
             */
            ->reject(fn (Parameter $p) => $paramsByName->keys()->some(fn (string $key) => Str::startsWith($key, $p->name.'.')))
            ->partition(function (Parameter $p) {
                if ($p->getAttribute('isFlat')) {
                    return true;
                }

                $isScalar = ! in_array($p->schema->type->type ?? null, ['array', 'object', null], strict: true);

                $isArrayOfScalar = ($p->schema->type ?? null) instanceof ArrayType
                    && ! in_array($p->schema->type->items->type ?? null, ['array', 'object', null], strict: true);

                if (! Str::contains($p->name, '*')) { // no nested arrays
                    return $isScalar || $isArrayOfScalar;
                }

                if (Str::endsWith($p->name, '*') && (Str::substrCount($p->name, '*') === 1)) {
                    return $isScalar;
                }

                return false;
            });

        $deepParameters = array_map(
            fn (Parameter $p) => tap($p, fn (Parameter $p) => $p->setExtensionProperty('deepObject-style', 'qs')),
            $this->convertDotNamedParamsToComplexStructures($deepParameters->all()),
        );

        return collect($convertableParameters)
            ->map(function (Parameter $originalParameter) use ($paramsByName) {
                $parameter = clone $originalParameter;

                $parameter->name = Str::of($parameter->name)
                    ->explode('.')
                    ->map(fn ($str, $i) => $i === 0 ? $str : ($str === '*' ? '[]' : "[$str]"))
                    ->join('');

                if ($parameter->schema->type instanceof ArrayType) {
                    $parameter->name .= '[]';
                }

                if (
                    $parameter->name !== $originalParameter->name
                    && ($sameNameParam = $paramsByName->get($parameter->name))
                    && $sameNameParam !== $originalParameter
                ) {
                    return null;
                }

                if (Str::endsWith($parameter->name, '[]') && ! $parameter->schema->type instanceof ArrayType) {
                    $parameter->schema->type = (new ArrayType)
                        ->setItems($parameter->schema->type)
                        ->addProperties($parameter->schema->type);
                }

                return $parameter;
            })
            ->filter()
            ->values()
            ->merge($deepParameters)
            ->all();
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

            return Str::contains($parameterString, '"contentMediaType":"application\/octet-stream"');
        });
    }

    private function extractParameters(Operation $operation, RouteInfo $routeInfo)
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
