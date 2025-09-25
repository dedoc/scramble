<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Reflection\ReflectionRoute;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type as InferType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use ReflectionParameter;

class PathParametersExtractor implements ParameterExtractor
{
    public function __construct(
        private TypeTransformer $openApiTransformer,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        $reflectionRoute = ReflectionRoute::createFromRoute($route = $routeInfo->route);

        $aliases = $reflectionRoute->getSignatureParametersMap();
        $routeParams = collect($route->signatureParameters());
        $reflectionParamsByKeys = $routeParams->keyBy->name;
        $paramsValuesClasses = $reflectionRoute->getBoundParametersTypes();
        $phpDocTypehintParam = collect($routeInfo->phpDoc()->getParamTagValues())->keyBy(fn (ParamTagValueNode $n) => Str::replace('$', '', $n->parameterName));

        /*
         * Figure out param type based on importance priority:
         * 1. Typehint (reflection)
         * 2. PhpDoc Typehint
         * 3. String (?)
         */
        $parameters = array_map(function (string $paramName) use ($routeInfo, $route, $aliases, $reflectionParamsByKeys, $phpDocTypehintParam, $paramsValuesClasses) {
            $originalParamName = $paramName;
            $paramName = $aliases[$paramName];

            $description = $phpDocTypehintParam[$paramName]?->description ?? '';
            [$schemaType, $description, $isOptional] = $this->getParameterType(
                $paramName,
                $description,
                $routeInfo,
                $route,
                $phpDocTypehintParam[$paramName] ?? null,
                $reflectionParamsByKeys[$paramName] ?? null,
                $paramsValuesClasses[$originalParamName] ?? null,
            );

            $param = Parameter::make($paramName, 'path')
                ->description($description)
                ->setSchema(Schema::fromType($schemaType));

            if ($isOptional) {
                $param->setExtensionProperty('optional', true);
            }

            $param->setAttribute('nonBody', true);

            return $param;
        }, array_values(array_diff($route->parameterNames(), $this->getParametersFromString($route->getDomain()))));

        return [...$parameterExtractionResults, new ParametersExtractionResult($parameters)];
    }

    private function getParametersFromString(?string $str)
    {
        return Str::of($str)->matchAll('/\{(.*?)\}/')->values()->toArray();
    }

    private function getParameterType(
        string $paramName,
        string $description,
        RouteInfo $routeInfo,
        Route $route,
        ?ParamTagValueNode $phpDocParam,
        ?ReflectionParameter $reflectionParam,
        ?string $boundClass,
    ) {
        $type = $boundClass ? new ObjectType($boundClass) : new UnknownType;
        if ($routeInfo->reflectionAction()) {
            $type->setAttribute('file', $routeInfo->reflectionAction()->getFileName());
            $type->setAttribute('line', $routeInfo->reflectionAction()->getStartLine());
        }

        if ($phpDocParam?->type) {
            $type = PhpDocTypeHelper::toType($phpDocParam->type);
        }

        if ($reflectionParam?->hasType()) {
            $type = TypeHelper::createTypeFromReflectionType($reflectionParam->getType());
        }

        $simplifiedType = Union::wrap(array_map(
            fn (InferType $t) => $t instanceof ObjectType
                ? (enum_exists($t->name) ? $t : new \Dedoc\Scramble\Support\Type\StringType)
                : $t,
            $type instanceof Union ? $type->types : [$type],
        ));

        $schemaType = $this->openApiTransformer->transform($simplifiedType);

        if ($isModelId = $type instanceof ObjectType) {
            [$schemaType, $description] = $this->getModelIdTypeAndDescription($schemaType, $type, $paramName, $description, $route->bindingFields()[$paramName] ?? null);

            $schemaType->setAttribute('isModelId', true);
        }

        if ($schemaType instanceof \Dedoc\Scramble\Support\Generator\Types\UnknownType) {
            $schemaType = (new StringType)->mergeAttributes($schemaType->attributes());
        }

        if ($reflectionParam?->isDefaultValueAvailable()) {
            $schemaType->default($reflectionParam->getDefaultValue());
        }

        $description ??= '';

        if ($isOptional = Str::contains($route->uri(), ['{'.$paramName.'?}', '{'.Str::snake($paramName).'?}'], ignoreCase: true)) {
            $description = implode('. ', array_filter(['**Optional**', $description]));
        }

        return [$schemaType, $description, $isOptional];
    }

    private function getModelIdTypeAndDescription(
        Type $baseType,
        InferType $type,
        string $paramName,
        string $description,
        ?string $bindingField,
    ): array {
        $defaults = [
            $baseType,
            $description ?: 'The '.Str::of($paramName)->kebab()->replace(['-', '_'], ' ').' ID',
        ];

        if (! $type->isInstanceOf(Model::class)) {
            return $defaults;
        }

        /** @var ObjectType $type */
        $defaults[0] = $this->openApiTransformer->transform(new IntegerType);

        try {
            /** @var Model $modelInstance */
            $modelInstance = resolve($type->name);
        } catch (BindingResolutionException) {
            return $defaults;
        }

        $modelKeyName = $modelInstance->getKeyName();
        $routeKeyName = $bindingField ?: $modelInstance->getRouteKeyName();

        if ($description === '') {
            $keyDescriptionName = in_array($routeKeyName, ['id', 'uuid'])
                ? Str::upper($routeKeyName)
                : (string) Str::of($routeKeyName)->lower()->kebab()->replace(['-', '_'], ' ');

            $description = 'The '.Str::of($paramName)->kebab()->replace(['-', '_'], ' ').' '.$keyDescriptionName;
        }

        $modelTraits = class_uses($type->name);
        if ($routeKeyName === $modelKeyName && Arr::hasAny($modelTraits, [HasUuids::class, HasVersion4Uuids::class])) {
            return [(new StringType)->format('uuid'), $description];
        }

        $propertyType = $type->getPropertyType($routeKeyName);
        if ($propertyType instanceof UnknownType) {
            $propertyType = new IntegerType;
        }

        return [$this->openApiTransformer->transform($propertyType), $description];
    }
}
