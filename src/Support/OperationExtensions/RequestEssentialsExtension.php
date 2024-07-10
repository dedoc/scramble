<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Generator\UniqueNameOptions;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\ServerFactory;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type as InferType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use ReflectionParameter;

class RequestEssentialsExtension extends OperationExtension
{
    public function __construct(
        Infer $infer,
        TypeTransformer $openApiTransformer,
        GeneratorConfig $config,
        private OpenApi $openApi,
        private ServerFactory $serverFactory
    ) {
        parent::__construct($infer, $openApiTransformer, $config);
    }

    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        [$pathParams, $pathAliases] = $this->getRoutePathParameters($routeInfo);

        $tagResolver = Scramble::$tagResolver ?? function (RouteInfo $routeInfo) {
            return array_unique([
                ...$this->extractTagsForMethod($routeInfo),
                Str::of(class_basename($routeInfo->className()))->replace('Controller', ''),
            ]);
        };

        $uriWithoutOptionalParams = Str::replace('?}', '}', $routeInfo->route->uri);

        $operation
            ->setMethod(strtolower($routeInfo->route->methods()[0]))
            ->setPath(Str::replace(
                collect($pathAliases)->keys()->map(fn ($k) => '{'.$k.'}')->all(),
                collect($pathAliases)->values()->map(fn ($v) => '{'.$v.'}')->all(),
                $uriWithoutOptionalParams,
            ))
            ->setTags($tagResolver($routeInfo, $operation))
            ->servers($this->getAlternativeServers($routeInfo->route))
            ->addParameters($pathParams);

        if (count($routeInfo->phpDoc()->getTagsByName('@unauthenticated'))) {
            $operation->addSecurity([]);
        }

        $operation->setAttribute('operationId', $this->getOperationId($routeInfo));
    }

    /**
     * Checks if route domain needs to have alternative servers defined. Route needs to have alternative servers defined if
     * the route has not matching domain to any servers in the root.
     *
     * Domain is matching if all the server variables matching.
     */
    private function getAlternativeServers(Route $route)
    {
        if (! $route->getDomain()) {
            return [];
        }

        [$protocol] = explode('://', url('/'));
        $expectedServer = $this->serverFactory->make($protocol.'://'.$route->getDomain().'/'.$this->config->get('api_path', 'api'));

        if ($this->isServerMatchesAllGivenServers($expectedServer, $this->openApi->servers)) {
            return [];
        }

        $matchingServers = collect($this->openApi->servers)->filter(fn (Server $s) => $this->isMatchingServerUrls($expectedServer->url, $s->url));
        if ($matchingServers->count()) {
            return $matchingServers->values()->toArray();
        }

        return [$expectedServer];
    }

    private function isServerMatchesAllGivenServers(Server $expectedServer, array $actualServers)
    {
        return collect($actualServers)->every(fn (Server $s) => $this->isMatchingServerUrls($expectedServer->url, $s->url));
    }

    private function isMatchingServerUrls(string $expectedUrl, string $actualUrl)
    {
        $mask = function (string $url) {
            [, $urlPart] = explode('://', $url);
            [$domain, $path] = count($parts = explode('/', $urlPart, 2)) !== 2 ? [$parts[0], ''] : $parts;

            $params = Str::of($domain)->matchAll('/\{(.*?)\}/');

            return $params->join('.').'/'.$path;
        };

        return $mask($expectedUrl) === $mask($actualUrl);
    }

    private function extractTagsForMethod(RouteInfo $routeInfo)
    {
        $classPhpDoc = $routeInfo->reflectionMethod()
            ? $routeInfo->reflectionMethod()->getDeclaringClass()->getDocComment()
            : false;

        $classPhpDoc = $classPhpDoc ? PhpDoc::parse($classPhpDoc) : new PhpDocNode([]);

        if (! count($tagNodes = $classPhpDoc->getTagsByName('@tags'))) {
            return [];
        }

        return explode(',', array_values($tagNodes)[0]->value->value);
    }

    private function getParametersFromString(?string $str)
    {
        return Str::of($str)->matchAll('/\{(.*?)\}/')->values()->toArray();
    }

    private function getRoutePathParameters(RouteInfo $routeInfo)
    {
        [$route, $methodPhpDocNode] = [$routeInfo->route, $routeInfo->phpDoc()];

        $paramNames = $route->parameterNames();
        $paramsWithRealNames = ($reflectionParams = collect($route->signatureParameters())
            ->filter(function (ReflectionParameter $v) {
                if (($type = $v->getType()) && ($type instanceof \ReflectionNamedType) && ($typeName = $type->getName())) {
                    if (is_a($typeName, Request::class, true)) {
                        return false;
                    }
                }

                return true;
            })
            ->values())
            ->map(fn (ReflectionParameter $v) => $v->name)
            ->all();

        if (count($paramNames) !== count($paramsWithRealNames)) {
            $paramsWithRealNames = $paramNames;
        }

        $aliases = collect($paramNames)->mapWithKeys(fn ($name, $i) => [$name => $paramsWithRealNames[$i]])->all();

        $reflectionParamsByKeys = $reflectionParams->keyBy->name;
        $phpDocTypehintParam = $methodPhpDocNode
            ? collect($methodPhpDocNode->getParamTagValues())->keyBy(fn (ParamTagValueNode $n) => Str::replace('$', '', $n->parameterName))
            : collect();

        /*
         * Figure out param type based on importance priority:
         * 1. Typehint (reflection)
         * 2. PhpDoc Typehint
         * 3. String (?)
         */
        $params = array_map(function (string $paramName) use ($routeInfo, $route, $aliases, $reflectionParamsByKeys, $phpDocTypehintParam) {
            $paramName = $aliases[$paramName];

            $description = $phpDocTypehintParam[$paramName]?->description ?? '';
            [$schemaType, $description, $isOptional] = $this->getParameterType(
                $paramName,
                $description,
                $routeInfo,
                $route,
                $phpDocTypehintParam[$paramName] ?? null,
                $reflectionParamsByKeys[$paramName] ?? null,
            );

            $param = Parameter::make($paramName, 'path')
                ->description($description)
                ->setSchema(Schema::fromType($schemaType));

            if ($isOptional) {
                $param->setExtensionProperty('optional', true);
            }

            return $param;
        }, array_values(array_diff($route->parameterNames(), $this->getParametersFromString($route->getDomain()))));

        return [$params, $aliases];
    }

    private function getParameterType(string $paramName, string $description, RouteInfo $routeInfo, Route $route, ?ParamTagValueNode $phpDocParam, ?ReflectionParameter $reflectionParam)
    {
        $type = new UnknownType;
        if ($routeInfo->reflectionMethod()) {
            $type->setAttribute('file', $routeInfo->reflectionMethod()->getFileName());
            $type->setAttribute('line', $routeInfo->reflectionMethod()->getStartLine());
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

        $isOptional = false;
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
        if ($routeKeyName === $modelKeyName && Arr::has($modelTraits, HasUuids::class)) {
            return [(new StringType)->format('uuid'), $description];
        }

        $propertyType = $type->getPropertyType($routeKeyName);
        if ($propertyType instanceof UnknownType) {
            $propertyType = new IntegerType;
        }

        return [$this->openApiTransformer->transform($propertyType), $description];
    }

    private function getOperationId(RouteInfo $routeInfo)
    {
        $routeClassName = $routeInfo->className() ?: '';

        return new UniqueNameOptions(
            eloquent: (function () use ($routeInfo) {
                // Manual operation ID setting.
                if (
                    ($operationId = $routeInfo->phpDoc()->getTagsByName('@operationId'))
                    && ($value = trim(Arr::first($operationId)?->value?->value))
                ) {
                    return $value;
                }

                // Using route name as operation ID if set. We need to avoid using generated route names as this
                // will result gibberish operation IDs when routes without names are cached.
                if (($name = $routeInfo->route->getName()) && ! Str::contains($name, 'generated::')) {
                    return Str::startsWith($name, 'api.') ? Str::replaceFirst('api.', '', $name) : $name;
                }

                // If no name and no operationId manually set, falling back to controller and method name (unique implementation).
                return null;
            })(),
            unique: collect(explode('\\', Str::endsWith($routeClassName, 'Controller') ? Str::replaceLast('Controller', '', $routeClassName) : $routeClassName))
                ->filter()
                ->push($routeInfo->methodName())
                ->map(function ($part) {
                    if ($part === Str::upper($part)) {
                        return Str::lower($part);
                    }

                    return Str::camel($part);
                })
                ->reject(fn ($p) => in_array(Str::lower($p), ['app', 'http', 'api', 'controllers', 'invoke']))
                ->values()
                ->toArray(),
        );
    }
}
