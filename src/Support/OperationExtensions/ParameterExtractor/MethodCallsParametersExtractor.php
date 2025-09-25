<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;

class MethodCallsParametersExtractor implements ParameterExtractor
{
    public function __construct(
        private TypeTransformer $openApiTransformer,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        /**
         * This is needed to be able to access requestParametersFromCalls property.
         */
        $routeInfo->getActionDefinition();

        $previouslyExtractedParameters = collect($parameterExtractionResults)->flatMap->parameters->keyBy('name');

        $inferredParameters = array_values(array_filter(
            $routeInfo->requestParametersFromCalls->data,
            fn (InferredParameter $p) => ! $previouslyExtractedParameters->has($p->name),
        ));

        $parameters = array_map(function (InferredParameter $p) use ($routeInfo) {
            $parameter = $p->toOpenApiParameter($this->openApiTransformer);

            $parameter->in = in_array(mb_strtolower($routeInfo->route->methods()[0]), RequestBodyExtension::HTTP_METHODS_WITHOUT_REQUEST_BODY)
                ? 'query'
                : 'body';

            if ($parameter->getAttribute('isInQuery')) {
                $parameter->in = 'query';
            }

            return $parameter;
        }, $inferredParameters);

        $extractedParameters = new ParametersExtractionResult($parameters);

        /*
         * Possible improvements here: using defaults when merging results, etc.
         */

        return [...$parameterExtractionResults, $extractedParameters];
    }
}
