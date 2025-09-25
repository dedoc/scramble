<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\Attributes\Example;
use Dedoc\Scramble\Attributes\MissingValue;
use Dedoc\Scramble\Attributes\Parameter as ParameterAttribute;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\MissingValue as OpenApiMissingValue;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\RouteInfo;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use ReflectionAttribute;
use ReflectionClass;

class AttributesParametersExtractor implements ParameterExtractor
{
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
            $automaticallyExtractedParameters->parameters = collect($automaticallyExtractedParameters->parameters)
                ->filter(fn (Parameter $p) => ! in_array("$p->name.$p->in", $extractedAttributes))
                ->values()
                ->all();
        }

        return [...$parameterExtractionResults, new ParametersExtractionResult($parameters)];
    }

    /**
     * @param  ParametersExtractionResult[]  $extractedParameters
     */
    private function createParameter(array $extractedParameters, ParameterAttribute $attribute, array $attributeArguments): Parameter
    {
        $attributeParameter = $this->createParameterFromAttribute($attribute);

        if (! $attribute->infer) {
            return $attributeParameter;
        }

        if (! $inferredParameter = $this->getParameterFromAutomaticallyInferred($extractedParameters, $attribute->in, $attribute->name)) {
            return $attributeParameter;
        }

        $parameter = clone $inferredParameter;

        $namedAttributes = $this->createNamedAttributes($attribute::class, $attributeArguments);

        foreach ($namedAttributes as $name => $attrValue) {
            if ($name === 'in' || $name === 'name') {
                continue;
            }

            if ($name === 'default') {
                $parameter->schema->type->default = $attrValue;
            }

            if ($name === 'type') {
                $parameter->schema->type = $attributeParameter->schema->type;
            }

            if ($name === 'format') {
                $parameter->schema->type->format = $attributeParameter->schema->type->format;
            }

            if ($name === 'deprecated') {
                $parameter->deprecated = $attributeParameter->deprecated;
            }

            if ($name === 'description') {
                $parameter->description = $attributeParameter->description;
            }

            if ($name === 'required') {
                $parameter->required = $attributeParameter->required;
            }

            if ($name === 'example') {
                $parameter->example = $attributeParameter->example;
            }

            if ($name === 'examples') {
                $parameter->examples = $attributeParameter->examples;
            }

            $parameter->setAttribute('nonBody', $attributeParameter->getAttribute('nonBody'));
        }

        return $parameter;
    }

    private function createParameterFromAttribute(ParameterAttribute $attribute): Parameter
    {
        $default = $attribute->default instanceof MissingValue ? new OpenApiMissingValue : $attribute->default;
        $type = $attribute->type ? $this->openApiTransformer->transform(
            PhpDocTypeHelper::toType(
                PhpDoc::parse("/** @return $attribute->type */")->getReturnTagValues()[0]->type ?? new IdentifierTypeNode('mixed')
            )
        ) : new MixedType;

        $parameter = Parameter::make($attribute->name, $attribute->in)
            ->description($attribute->description ?: '')
            ->setSchema(Schema::fromType(
                $type->default($default) // @phpstan-ignore argument.type
            ))
            ->required($attribute->required);

        $parameter->setAttribute('nonBody', $attribute->in !== 'body');

        $parameter->deprecated = $attribute->deprecated;

        if (! $attribute->example instanceof MissingValue) {
            $parameter->example = $attribute->example;
        }

        if ($attribute->examples) {
            $parameter->examples = array_map(
                fn (Example $e) => Example::toOpenApiExample($e),
                $attribute->examples,
            );
        }

        if ($attribute->format) {
            $type->format = $attribute->format;
        }

        return $parameter;
    }

    /**
     * @param  ParametersExtractionResult[]  $extractedParameters
     */
    private function getParameterFromAutomaticallyInferred(array $extractedParameters, string $in, string $name): ?Parameter
    {
        foreach ($extractedParameters as $automaticallyExtractedParameters) {
            foreach ($automaticallyExtractedParameters->parameters as $parameter) {
                if (
                    $parameter->in === $in
                    && $parameter->name === $name
                ) {
                    return $parameter;
                }
            }
        }

        return null;
    }

    private function createNamedAttributes(string $class, array $attributeArguments): array
    {
        $reflectionClass = new ReflectionClass($class);

        if (! $reflectionConstructor = $reflectionClass->getConstructor()) {
            return $attributeArguments;
        }

        $constructorParameters = $reflectionConstructor->getParameters();

        return collect($attributeArguments)
            ->mapWithKeys(function ($value, $key) use ($constructorParameters) {
                $name = is_string($key) ? $key : $constructorParameters[$key]->getName();

                return [$name => $value];
            })
            ->all();
    }
}
