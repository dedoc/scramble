<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Attributes\Parameter as ParameterAttribute;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use ReflectionAttribute;

class AttributesParametersExtractor implements ParameterExtractor
{
    use CreatesParametersFromAttributes;

    public function __construct(
        private TypeTransformer $openApiTransformer,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        if (! $reflectionAction = $routeInfo->reflectionAction()) {
            return $parameterExtractionResults;
        }

        $parameters = collect($reflectionAction->getAttributes(ParameterAttribute::class, ReflectionAttribute::IS_INSTANCEOF))
            ->values()
            ->map(fn (ReflectionAttribute $ra) => $this->createParameter($parameterExtractionResults, $ra->newInstance(), $ra->getArguments()))
            ->all();

        $extractedAttributes = collect($parameters)->map(fn ($p) => "$p->name.$p->in")->all();

        foreach ($parameterExtractionResults as $automaticallyExtractedParameters) {
            // Named results map to a reusable component schema. Stripping fields from them would corrupt
            // that shared schema for every other operation that references the same FormRequest.
            // Action-level attributes are appended as a separate schemaless result instead,
            // so RequestBodyExtension composes them as an allOf overlay on top of the intact $ref.
            if ($automaticallyExtractedParameters->schemaName) {
                continue;
            }

            $automaticallyExtractedParameters->parameters = collect($automaticallyExtractedParameters->parameters)
                ->filter(fn (Parameter $p) => ! in_array("$p->name.$p->in", $extractedAttributes))
                ->values()
                ->all();
        }

        return [...$parameterExtractionResults, new ParametersExtractionResult($parameters)];
    }
}
