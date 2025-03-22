<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;

class MethodCallsParametersExtractor implements ParameterExtractor
{
    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        $parameters = array_values($routeInfo->requestParametersFromCalls->data);

        foreach ($parameters as $parameter) {
            $parameter->in = in_array(mb_strtolower($routeInfo->route->methods()[0]), RequestBodyExtension::HTTP_METHODS_WITHOUT_REQUEST_BODY)
                ? 'query'
                : 'body';

            if ($parameter->getAttribute('isInQuery')) {
                $parameter->in = 'query';
            }
        }

        $extractedParameters = new ParametersExtractionResult($parameters);

        $previouslyExtractedParameters = collect($parameterExtractionResults)->flatMap->parameters->keyBy('name');

        $extractedParameters->parameters = collect($extractedParameters->parameters)
            ->filter(fn (Parameter $p) => ! $previouslyExtractedParameters->has($p->name))
            ->values()
            ->all();

        /*
         * Possible improvements here: using defaults when merging results, etc.
         */

        return [...$parameterExtractionResults, $extractedParameters];
    }
}
