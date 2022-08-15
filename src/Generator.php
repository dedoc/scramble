<?php

namespace Dedoc\Documentor;

use Dedoc\Documentor\Support\Generator\InfoObject;
use Dedoc\Documentor\Support\Generator\OpenApi;
use Dedoc\Documentor\Support\Generator\Operation;
use Dedoc\Documentor\Support\Generator\Parameter;
use Dedoc\Documentor\Support\Generator\Path;
use Dedoc\Documentor\Support\Generator\RequestBodyObject;
use Dedoc\Documentor\Support\Generator\Schema;
use Dedoc\Documentor\Support\Generator\Types\BooleanType;
use Dedoc\Documentor\Support\Generator\Types\IntegerType;
use Dedoc\Documentor\Support\Generator\Types\NumberType;
use Dedoc\Documentor\Support\Generator\Types\StringType;
use Dedoc\Documentor\Support\PhpDoc;
use Dedoc\Documentor\Support\ResponseExtractor\ResponsesExtractor;
use Dedoc\Documentor\Support\RulesExtractor\FormRequestRulesExtractor;
use Dedoc\Documentor\Support\RulesExtractor\ValidateCallExtractor;
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
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use ReflectionClass;

class Generator
{
    public function __invoke()
    {
        $routes = $this->getRoutes();

        /** @var OpenApi $openApi */
        $openApi = $this->makeOpenApi();

        $routes//->dd()
            ->map(fn (Route $route) => $this->routeToOperation($openApi, $route))
            ->eachSpread(fn (string $path, Operation $operation) => $openApi->addPath(
                Path::make(str_replace('api/', '', $path))->addOperation($operation)
            ))
            ->toArray();

        return $openApi->toArray();
    }

    private function makeOpenApi()
    {
        return OpenApi::make('3.1.0')
            ->addInfo(new InfoObject(config('app.name')));
    }

    private function getRoutes(): Collection
    {
        return collect(RouteFacade::getRoutes())//->dd()
            // Now care only about API routes
            ->filter(function (Route $route) {
                return !($name = $route->getAction('as')) || !Str::startsWith($name, 'documentor');
            })
//            ->filter(fn (Route $route) => Str::contains($route->uri, 'impersonate'))
//            ->filter(fn (Route $route) => $route->uri === 'api/brand/{brand}/publishers/{publisher}'&& $route->methods()[0] === 'GET')
//            ->filter(fn (Route $route) => $route->uri === 'api/slack-conversations'&& $route->methods()[0] === 'GET')
//            ->filter(fn (Route $route) => $route->uri === 'api/campaigns'&& $route->methods()[0] === 'POST')
//            ->filter(fn (Route $route) => $route->uri === 'api/creators/{creator}'&& $route->methods()[0] === 'PUT')
//            ->filter(fn (Route $route) => $route->uri === 'api/event-production/{event_production}/brief' && $route->methods()[0] === 'POST')
//            ->filter(fn (Route $route) => $route->uri === 'api/event-production/{event_production}' && $route->methods()[0] === 'PUT')
//            ->filter(fn (Route $route) => $route->uri === 'api/todo-item' && $route->methods()[0] === 'POST')
            ->filter(fn (Route $route) => in_array('api', $route->gatherMiddleware()))
//            ->filter(fn (Route $route) => Str::contains($route->getAction('as'), 'api.creators.update'))
            ->values();
    }

    private function routeToOperation(OpenApi $openApi, Route $route)
    {
        /** @var Node\Stmt\ClassMethod|null $methodNode */
        /** @var PhpDocNode|null $methodPhpDocNode */
        /** @var \ReflectionMethod|null $reflectionMethod */
        [$methodNode, $methodPhpDocNode, $reflectionMethod, $classAliasesMap] = $this->extractNodes($route);

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
                $description = Str::of($text[0]);
            }
        }

        [$pathParams, $pathAliases] = $this->getRoutePathParameters($route, $methodPhpDocNode);

        $operation = Operation::make($method = strtolower($route->methods()[0]))
            // @todo: Not always correct/expected in real projects.
//            ->setOperationId(Str::camel($route->getAction('as')))
            ->setTags(
                // @todo: Fix real mess in the real projects
                $route->getAction('controller')
                    ? [Str::of(get_class($route->controller))->explode('\\')->mapInto(Stringable::class)->last()->replace('Controller', '')]
                    : []
            )
            // @todo: Figure out when params are for the implicit/explicit model binding and type them appropriately
            // @todo: Use route function typehints to get the primitive types
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
            } else {
//            $operation
//                ->addRequesBodyObject(
//                    RequestBodyObject::make()
//                        ->setContent(
//                            'application/json',
//                            Schema::createFromArray([])
//                        )
//                );
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

        return [
            Str::replace(array_keys($pathAliases), array_values($pathAliases), $route->uri),
            $operation,
        ];
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

        // @todo: support closures, not only "Controller@action" string
        if (is_string($uses = $route->getAction('uses'))) {
            [$class, $method] = explode('@', $uses);
            $reflectionClass = new ReflectionClass($class);
            $classSourceCode = file_get_contents($reflectionClass->getFileName());
            $classAst = (new ParserFactory)->create(ParserFactory::PREFER_PHP7)->parse($classSourceCode);

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

        return [$methodNode, $methodPhpDocNode, $reflectionMethod, $aliases];
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
