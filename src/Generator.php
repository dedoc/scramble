<?php

namespace Dedoc\ApiDocs;

use Dedoc\ApiDocs\Support\ComplexTypeHandler\ComplexTypeHandlers;
use Dedoc\ApiDocs\Support\Generator\InfoObject;
use Dedoc\ApiDocs\Support\Generator\OpenApi;
use Dedoc\ApiDocs\Support\Generator\Operation;
use Dedoc\ApiDocs\Support\Generator\Parameter;
use Dedoc\ApiDocs\Support\Generator\Path;
use Dedoc\ApiDocs\Support\Generator\RequestBodyObject;
use Dedoc\ApiDocs\Support\Generator\Schema;
use Dedoc\ApiDocs\Support\Generator\Server;
use Dedoc\ApiDocs\Support\Generator\Types\BooleanType;
use Dedoc\ApiDocs\Support\Generator\Types\IntegerType;
use Dedoc\ApiDocs\Support\Generator\Types\NumberType;
use Dedoc\ApiDocs\Support\Generator\Types\ObjectType;
use Dedoc\ApiDocs\Support\Generator\Types\StringType;
use Dedoc\ApiDocs\Support\PhpDoc;
use Dedoc\ApiDocs\Support\ResponseExtractor\ResponsesExtractor;
use Dedoc\ApiDocs\Support\RulesExtractor\FormRequestRulesExtractor;
use Dedoc\ApiDocs\Support\RulesExtractor\ValidateCallExtractor;
use Dedoc\ApiDocs\Support\Type\Identifier;
use Dedoc\ApiDocs\Support\TypeHandlers\TypeHandlers;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\FirstFindingVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use ReflectionClass;

class Generator
{
    public function __invoke()
    {
        $openApi = $this->makeOpenApi();

        ComplexTypeHandlers::registerComponentsRepository($openApi->components);

        $this->getRoutes()
            ->map(fn (Route $route) => $this->routeToOperation($openApi, $route))
            ->eachSpread(fn (string $path, Operation $operation) => $openApi->addPath(
                Path::make(str_replace('api/', '', $path))->addOperation($operation)
            ))
            ->toArray();

        if (isset(ApiDocs::$openApiExtender)) {
            $openApi = (ApiDocs::$openApiExtender)($openApi);
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
            ->filter(function (Route $route) {
                return ! ($name = $route->getAction('as')) || ! Str::startsWith($name, 'api-docs');
            })
            ->filter(function (Route $route) {
                $routeResolver = ApiDocs::$routeResolver ?? fn (Route $route) => in_array('api', $route->gatherMiddleware());

                return $routeResolver($route);
            })
            ->values();
    }

    private function routeToOperation(OpenApi $openApi, Route $route)
    {
        /** @var Node\Stmt\ClassMethod|null $methodNode */
        /** @var PhpDocNode|null $methodPhpDocNode */
        /** @var \ReflectionMethod|null $reflectionMethod */
        /** @var PhpDocNode|null $aliasPhpDocNode */
        [$methodNode, $methodPhpDocNode, $reflectionMethod, $classAliasesMap, $aliasPhpDocNode] = $this->extractNodes($route);

        if ($methodNode) {
            $name = explode($route->getAction('uses'), '@')[0];

            TypeHandlers::registerIdentifierHandler($name, function (string $name) use ($classAliasesMap) {
                $fqName = $classAliasesMap[$name] ?? $name;

                return ComplexTypeHandlers::handle(new Identifier($fqName));
            });
        }

        $summary = Str::of('');
        $description = Str::of('');

        if ($methodPhpDocNode) {
            $text = collect($methodPhpDocNode->children)
                ->filter(fn ($v) => $v instanceof PhpDocTextNode)
                ->map(fn (PhpDocTextNode $n) => $n->text)
                ->implode("\n");

            $text = Str::of($text)
                ->trim()
                ->explode("\n\n", 2);

            if (count($text) === 2) {
                $summary = Str::of($text[0]);
                $description = Str::of($text[1]);
            } elseif (count($text) === 1) {
                $summary = Str::of($text[0]);
            }
        }

        [$pathParams, $pathAliases] = $this->getRoutePathParameters($route, $methodPhpDocNode);

        $operation = Operation::make($method = strtolower($route->methods()[0]))
            ->setTags(array_merge(
                $this->extractTagsForMethod($aliasPhpDocNode),
                $route->getAction('controller')
                    ? [Str::of(get_class($route->controller))->explode('\\')->mapInto(Stringable::class)->last()->replace('Controller', '')]
                    : []
            ))
            ->addParameters($pathParams);

        /** @var Parameter[] $bodyParams */
        try {
            if (count($bodyParams = $this->extractParamsFromRequestValidationRules($route, $methodNode, $classAliasesMap))) {
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
            $description = $description->append('⚠️Cannot generate request documentation: '.$exception->getMessage());
        }

        $responses = (new ResponsesExtractor($openApi, $route, $methodNode, $reflectionMethod, $methodPhpDocNode, $classAliasesMap))();
        foreach ($responses as $response) {
            $operation->addResponse($response);
        }

        $operation
            ->summary($summary->rtrim('.'))
            ->description($description);

        if (isset(ApiDocs::$operationResolver)) {
            (ApiDocs::$operationResolver)($operation, $route, $methodNode, $reflectionMethod, $methodPhpDocNode);
        }

        return [
            Str::replace(array_keys($pathAliases), array_values($pathAliases), $route->uri),
            $operation,
        ];
    }

    private function extractTagsForMethod(?PhpDocNode $classPhpDocNode)
    {
        if (! $classPhpDocNode) {
            return [];
        }

        if (! count($tagNodes = $classPhpDocNode->getTagsByName('@tags'))) {
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

    private function extractParamsFromRequestValidationRules(Route $route, ?Node\Stmt\ClassMethod $methodNode, $classAliasesMap)
    {
        $rules = $this->extractRouteRequestValidationRules($route, $methodNode, $classAliasesMap);

        if (! $rules) {
            return [];
        }

        return collect($rules)
            ->map(function ($rules, $name) {
                $rules = Arr::wrap(is_string($rules) ? explode('|', $rules) : $rules);
                $rules = array_map(
                    fn ($v) => method_exists($v, '__toString') ? $v->__toString() : $v,
                    $rules,
                );

                $type = new StringType;
                $description = '';
                $enum = [];

                if (in_array('bool', $rules) || in_array('boolean', $rules)) {
                    $type = new BooleanType;
                } elseif (in_array('numeric', $rules)) {
                    $type = new NumberType;
                } elseif (in_array('integer', $rules) || in_array('int', $rules)) {
                    $type = new IntegerType;
                }

                if (collect($rules)->contains(fn ($v) => is_string($v) && Str::is('exists:*,id*', $v))) {
                    $type = new IntegerType;
                }

                if ($inRule = collect($rules)->first(fn ($v) => is_string($v) && Str::is('in:*', $v))) {
                    $enum = Str::of($inRule)
                        ->replaceFirst('in:', '')
                        ->explode(',')
                        ->mapInto(Stringable::class)
                        ->map(fn (Stringable $v) => (string) $v->trim('"')->replace('""', '"'))
                        ->values()->all();
                }

                if (in_array('nullable', $rules)) {
                    $type->nullable(true);
                }

                if ($type instanceof NumberType) {
                    if ($min = Str::replace('min:', '', collect($rules)->first(fn ($v) => is_string($v) && Str::startsWith($v, 'min:'), ''))) {
                        $type->setMin((float) $min);
                    }
                    if ($max = Str::replace('max:', '', collect($rules)->first(fn ($v) => is_string($v) && Str::startsWith($v, 'max:'), ''))) {
                        $type->setMax((float) $max);
                    }
                }

                return Parameter::make($name, 'query')
                    ->setSchema(Schema::fromType($type)->enum($enum))
                    ->required(in_array('required', $rules))
                    ->description($description);
            })
            ->values()
            ->all();
    }

    private function extractRouteRequestValidationRules(Route $route, $methodNode, $classAliasesMap)
    {
        // Custom form request's class `validate` method
        if (($formRequestRulesExtractor = new FormRequestRulesExtractor($methodNode))->shouldHandle()) {
            return $formRequestRulesExtractor->extract($route);
        }

        if (($validateCallExtractor = new ValidateCallExtractor($methodNode, $classAliasesMap))->shouldHandle()) {
            return $validateCallExtractor->extract($route);
        }

        return null;
    }

    private function extractNodes(Route $route)
    {
        /** @var Node\Stmt\ClassMethod|null $methodNode */
        $methodNode = null;
        /** @var PhpDocNode|null $methodPhpDocNode */
        $methodPhpDocNode = null;
        /** @var \ReflectionMethod|null $reflectionMethod */
        $reflectionMethod = null;
        $aliases = [];
        /** @var PhpDocNode|null $aliasPhpDocNode */
        $aliasPhpDocNode = null;

        // @todo: support closures, not only "Controller@action" string
        if (is_string($uses = $route->getAction('uses'))) {
            [$class, $method] = explode('@', $uses);
            $reflectionClass = new ReflectionClass($class);
            $classSourceCode = file_get_contents($reflectionClass->getFileName());
            $classAst = (new ParserFactory)->create(ParserFactory::PREFER_PHP7)->parse($classSourceCode);

            if ($classComment = $reflectionClass->getDocComment()) {
                $aliasPhpDocNode = PhpDoc::parse($classComment);
            }

            /** @var Node\Stmt\ClassMethod $methodNode */
            [$methodNode, $aliases] = $this->findFirstNode(
                $classAst,
                fn (Node $node) => $node instanceof Node\Stmt\ClassMethod && $node->name->name === $method
            );

            if ($methodNode) {
                $reflectionMethod = $reflectionClass->getMethod($method);

                if ($docComment = $reflectionMethod->getDocComment()) {
                    $methodPhpDocNode = PhpDoc::parse($docComment);
                }
            }
        }

        return [$methodNode, $methodPhpDocNode, $reflectionMethod, $aliases, $aliasPhpDocNode];
    }

    private function findFirstNode(?array $classAst, \Closure $param)
    {
        $visitor = new FirstFindingVisitor($param);

        $nameResolver = new NameResolver();
        $traverser = new NodeTraverser;
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);
        $traverser->traverse($classAst);

        /** @var Node\Stmt\ClassMethod $methodNode */
        $methodNode = $visitor->getFoundNode();

        // @todo Fix dirty way of getting the map of aliases
        $context = $nameResolver->getNameContext();
        $reflection = new ReflectionClass($context);
        $property = $reflection->getProperty('origAliases');
        $property->setAccessible(true);
        $value = $property->getValue($context);
        $aliases = array_map(fn (Node\Name $n) => $n->toCodeString(), $value[1]);

        return [$methodNode, $aliases];
    }
}
