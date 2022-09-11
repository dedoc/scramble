<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\BuiltInExtensions\AnonymousResourceCollectionTypeToSchema;
use Dedoc\Scramble\Support\BuiltInExtensions\JsonResourceTypeToSchema;
use Dedoc\Scramble\Support\BuiltInExtensions\LengthAwarePaginatorTypeToSchema;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Infer\Infer;
use Dedoc\Scramble\Support\ResponseExtractor\ResponsesExtractor;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\RulesExtractor\FormRequestRulesExtractor;
use Dedoc\Scramble\Support\RulesExtractor\RulesToParameter;
use Dedoc\Scramble\Support\RulesExtractor\ValidateCallExtractor;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class Generator
{
    private TypeTransformer $transformer;

    const NATIVE_TYPES_TO_OPEN_API_EXTENSIONS = [
        JsonResourceTypeToSchema::class,
        AnonymousResourceCollectionTypeToSchema::class,
        LengthAwarePaginatorTypeToSchema::class,
    ];

    private function initTransformer(OpenApi $openApi)
    {
        $extensions = config('scramble.extensions', []);

        $typesToOpenApiSchemaExtensions = array_values(array_filter(
            $extensions,
            fn ($e) => is_a($e, TypeToSchemaExtension::class, true),
        ));
        $typeInferringExtensions = [];

        $this->transformer = new TypeTransformer(
            new Infer,
            $openApi->components,
            array_merge(
                static::NATIVE_TYPES_TO_OPEN_API_EXTENSIONS,
                $typesToOpenApiSchemaExtensions,
            ),
        );
    }

    public function __invoke()
    {
        $openApi = $this->makeOpenApi();

        $this->initTransformer($openApi);

        $this->getRoutes()
            ->map(fn (Route $route) => $this->routeToOperation($openApi, $route))
            ->filter() // Closure based routes are filtered out for now
            ->eachSpread(fn (string $path, Operation $operation) => $openApi->addPath(
                Path::make(str_replace('api/', '', $path))->addOperation($operation)
            ))
            ->toArray();

        if (isset(Scramble::$openApiExtender)) {
            $openApi = (Scramble::$openApiExtender)($openApi);
        }

        return $openApi->toArray();
    }

    private function makeOpenApi()
    {
        $openApi = OpenApi::make('3.1.0')
            ->addInfo(InfoObject::make(config('app.name'))->setVersion('0.0.1'));

        $openApi->addServer(Server::make(url('/api')));

        return $openApi;
    }

    private function getRoutes(): Collection
    {
        return collect(RouteFacade::getRoutes())
            ->pipe(function (Collection $c) {
                $onlyRoute = $c->first(function (Route $route) {
                    if (! is_string($route->getAction('uses'))) {
                        return false;
                    }
                    try {
                        $reflection = new \ReflectionMethod(...explode('@', $route->getAction('uses')));

                        if (str_contains($reflection->getDocComment() ?: '', '@only-docs')) {
                            return true;
                        }
                    } catch (\Throwable $e) {
                    }

                    return false;
                });

                return $onlyRoute ? collect([$onlyRoute]) : $c;
            })
            ->filter(function (Route $route) {
                return ! ($name = $route->getAction('as')) || ! Str::startsWith($name, 'scramble');
            })
            ->filter(function (Route $route) {
                $routeResolver = Scramble::$routeResolver ?? fn (Route $route) => in_array('api', $route->gatherMiddleware());

                return $routeResolver($route);
            })
            ->values();
    }

    private function routeToOperation(OpenApi $openApi, Route $route)
    {
        $routeInfo = new RouteInfo($route);

        if (! $routeInfo->isClassBased()) {
            return null;
        }

        [$pathParams, $pathAliases] = $this->getRoutePathParameters($route, $routeInfo->phpDoc());

        $operation = Operation::make($method = strtolower($route->methods()[0]))
            ->setTags(array_merge(
                $this->extractTagsForMethod($routeInfo->class->phpDoc()),
                [Str::of(class_basename($routeInfo->className()))->replace('Controller', '')],
            ))
            ->addParameters($pathParams);

        $description = Str::of($routeInfo->phpDoc()->getAttribute('description'));
        try {
            if (count($bodyParams = $this->extractParamsFromRequestValidationRules($route, $routeInfo->methodNode()))) {
                if ($method !== 'get') {
                    $operation->addRequestBodyObject(
                        RequestBodyObject::make()->setContent('application/json', Schema::createFromParameters($bodyParams))
                    );
                } else {
                    $operation->addParameters($bodyParams);
                }
            } elseif ($method !== 'get') {
                $operation
                    ->addRequestBodyObject(
                        RequestBodyObject::make()
                            ->setContent(
                                'application/json',
                                Schema::fromType(new ObjectType)
                            )
                    );
            }
        } catch (\Throwable $exception) {
            throw $exception;
            $description = $description->append('⚠️Cannot generate request documentation: '.$exception->getMessage());
        }

        $responses = (new ResponsesExtractor($routeInfo, $this->transformer))();
        foreach ($responses as $response) {
            $operation->addResponse($response);
        }

        $operation
            ->summary(Str::of($routeInfo->phpDoc()->getAttribute('summary'))->rtrim('.'))
            ->description($description);

        if (isset(Scramble::$operationResolver)) {
            (Scramble::$operationResolver)($operation, $routeInfo);
        }

        return [
            Str::replace(array_keys($pathAliases), array_values($pathAliases), $route->uri),
            $operation,
        ];
    }

    private function extractTagsForMethod(PhpDocNode $classPhpDoc)
    {
        if (! count($tagNodes = $classPhpDoc->getTagsByName('@tags'))) {
            return [];
        }

        return explode(',', $tagNodes[0]->value->value);
    }

    private function getRoutePathParameters(Route $route, ?PhpDocNode $methodPhpDocNode)
    {
        $paramNames = $route->parameterNames();
        $paramsWithRealNames = ($reflectionParams = collect($route->signatureParameters())
            ->filter(function (\ReflectionParameter $v) {
                if (($type = $v->getType()) && $typeName = $type->getName()) {
                    if (is_a($typeName, Request::class, true)) {
                        return false;
                    }
                }

                return true;
            })
            ->values())
            ->map(fn (\ReflectionParameter $v) => $v->name)
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
        $params = array_map(function (string $paramName) use ($aliases, $reflectionParamsByKeys, $phpDocTypehintParam) {
            $paramName = $aliases[$paramName];

            $description = '';
            $type = null;

            if (isset($reflectionParamsByKeys[$paramName]) || isset($phpDocTypehintParam[$paramName])) {
                /** @var ParamTagValueNode $docParam */
                if ($docParam = $phpDocTypehintParam[$paramName] ?? null) {
                    if ($docType = $docParam->type) {
                        $type = (string) $docType;
                    }
                    if ($docParam->description) {
                        $description = $docParam->description;
                    }
                }

                if (
                    ($reflectionParam = $reflectionParamsByKeys[$paramName] ?? null)
                    && ($reflectionParam->hasType())
                ) {
                    /** @var \ReflectionParameter $reflectionParam */
                    $type = $reflectionParam->getType()->getName();
                }
            }

            $schemaTypesMap = [
                'int' => new IntegerType(),
                'float' => new NumberType(),
                'string' => new StringType(),
                'bool' => new BooleanType(),
            ];
            $schemaType = $type ? ($schemaTypesMap[$type] ?? new IntegerType) : new StringType;

            if ($type && ! isset($schemaTypesMap[$type]) && $description === '') {
                $description = 'The '.Str::of($paramName)->kebab()->replace(['-', '_'], ' ').' ID';
            }

            return Parameter::make($paramName, 'path')
                ->description($description)
                ->setSchema(Schema::fromType($schemaType));
        }, $route->parameterNames());

        return [$params, $aliases];
    }

    private function extractParamsFromRequestValidationRules(Route $route, ?Node\Stmt\ClassMethod $methodNode)
    {
        $rules = $this->extractRouteRequestValidationRules($route, $methodNode);

        if (! $rules) {
            return [];
        }

        return collect($rules)
            ->map(function ($rules, $name) {
                return (new RulesToParameter($name, $rules))->generate();
            })
            ->values()
            ->all();
    }

    private function extractRouteRequestValidationRules(Route $route, $methodNode)
    {
        // Custom form request's class `validate` method
        if (($formRequestRulesExtractor = new FormRequestRulesExtractor($methodNode))->shouldHandle()) {
            if (count($rules = $formRequestRulesExtractor->extract($route))) {
                return $rules;
            }
        }

        if (($validateCallExtractor = new ValidateCallExtractor($methodNode))->shouldHandle()) {
            return $validateCallExtractor->extract($route);
        }

        return null;
    }
}
