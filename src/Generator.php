<?php

namespace Dedoc\Documentor;

use Dedoc\Documentor\Support\Generator\InfoObject;
use Dedoc\Documentor\Support\Generator\OpenApi;
use Dedoc\Documentor\Support\Generator\Operation;
use Dedoc\Documentor\Support\Generator\Parameter;
use Dedoc\Documentor\Support\Generator\Path;
use Dedoc\Documentor\Support\Generator\RequestBodyObject;
use Dedoc\Documentor\Support\Generator\Response;
use Dedoc\Documentor\Support\Generator\Schema;
use Dedoc\Documentor\Support\Generator\Types\BooleanType;
use Dedoc\Documentor\Support\Generator\Types\IntegerType;
use Dedoc\Documentor\Support\Generator\Types\NumberType;
use Dedoc\Documentor\Support\Generator\Types\StringType;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\FirstFindingVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
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
            ->map(fn (Route $route) => [$route->uri, $this->routeToOperation($route)])
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
                $name = $route->getAction('as');
                if (! $name) {
                    return true;
                }

                return ! Str::startsWith($name, 'documentor');
            })
            ->filter(fn (Route $route) => Str::contains($route->uri, 'impersonate'))
//            ->filter(fn (Route $route) => $route->uri === 'api/event-production/{event_production}'&& $route->methods()[0] === 'PUT')
            ->filter(fn (Route $route) => in_array('api', $route->gatherMiddleware()))
//            ->filter(fn (Route $route) => Str::contains($route->getAction('as'), 'api.creators.update'))
            ->values();
    }

    private function routeToOperation(Route $route)
    {
        $summary = Str::of('');
        $description = Str::of('');

        $methodPhpDocNode = null;
        if ($route->getAction('uses')) {
            [$className, $method] = explode('@', $route->getAction('uses'));

            if (method_exists($className, $method)) {
                $reflection = new ReflectionClass($className);
                $method = $reflection->getMethod($method);

                if ($docComment = $method->getDocComment()) {
                    $lexer = new Lexer();
                    $constExprParser = new ConstExprParser();
                    $typeParser = new TypeParser($constExprParser);
                    $phpDocParser = new PhpDocParser($typeParser, $constExprParser);

                    $tokens = new TokenIterator($lexer->tokenize($docComment));
                    $methodPhpDocNode = $phpDocParser->parse($tokens);

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
            }
        }

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
            ->addParameters($this->getRoutePathParameters($route, $methodPhpDocNode));

        /** @var Parameter[] $bodyParams */
        try {
            if (count($bodyParams = $this->extractParamsFromRequestValidationRules($route))) {
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

        $operation
            ->addResponse(
                Response::make(200)
                    ->setContent(
                        'application/json',
                        Schema::createFromArray([])
                    )
            );

        return $operation
            ->summary($summary)
            ->description($description);
    }

    private function getRoutePathParameters(Route $route, PhpDocNode $methodPhpDocNode)
    {
        $paramNames = $route->parameterNames();
        $paramsWithRealNames = collect($route->signatureParameters())
            ->filter(function (\ReflectionParameter $v) {
                if (($type = $v->getType()) && $typeName = $type->getName()) {
                    if (is_a($typeName, Request::class, true)) {
                        return false;
                    }
                }
                return true;
            })
            ->values()
            ->map(fn (\ReflectionParameter $v) => $v->name)
            ->all();

        if (count($paramNames) !== count($paramsWithRealNames)) {
            $paramsWithRealNames = $paramNames;
        }

        $aliases = collect($paramNames)->mapWithKeys(fn ($name, $i) => [$name => $paramsWithRealNames[$i]])->all();

//        dd(
//            $aliases,
//            $methodPhpDocNode,
//        );

        return array_map(
            fn (string $paramName) => Parameter::make($paramName, 'path')->setSchema(Schema::fromType(new StringType)),
            $route->parameterNames()
        );
    }

    private function extractParamsFromRequestValidationRules(Route $route)
    {
        $rules = $this->extractRouteRequestValidationRules($route);

        if (! $rules) {
            return [];
        }

        return collect($rules)//->dd()
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
                } elseif (in_array('integer', $rules)) {
                    $type = new IntegerType;
                }

                if (collect($rules)->contains(fn ($v) => Str::is('exists:*,id*', $v))) {
                    $type = new IntegerType;
                }

                if ($inRule = collect($rules)->first(fn ($v) => Str::is('in:*', $v))) {
                    $enum = Str::of($inRule)
                        ->replaceFirst('in:', '')
                        ->explode(',')
                        ->mapInto(Stringable::class)
                        ->map(fn (Stringable $v) => (string) $v
                            ->trim('"')
                            ->replace('""', '"')
                        )->values()->all();
                }

                if (in_array('nullable', $rules)) {
                    $type->nullable(true);
                }

                if ($type instanceof NumberType) {
                    if ($min = Str::replace('min:', '', collect($rules)->first(fn ($v) => Str::startsWith($v, 'min:'), ''))) {
                        $type->setMin((float) $min);
                    }
                    if ($max = Str::replace('max:', '', collect($rules)->first(fn ($v) => Str::startsWith($v, 'max:'), ''))) {
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

    private function extractRouteRequestValidationRules(Route $route)
    {
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

        // @todo: support closures, not only "Controller@action" string
        if (! is_string($uses = $route->getAction('uses'))) {
            return null;
        }

        [$class, $method] = explode('@', $uses);

        $reflectionClass = new ReflectionClass($class);

        $classSourceCode = file_get_contents($reflectionClass->getFileName());
        $classAst = $parser->parse($classSourceCode);

        /** @var Node\Stmt\ClassMethod $methodNode */
        /** @var NameResolver $nameResolver */
        [$methodNode, $aliases] = $this->findFirstNode(
            $classAst,
            fn (Node $node) => $node instanceof Node\Stmt\ClassMethod && $node->name->name === $method
        );

//        dd($methodNode);

        // $request->validate, when $request is a Request instance
        /** @var Node\Expr\MethodCall $callToValidate */
        $callToValidate = (new NodeFinder())->findFirst(
            $methodNode,
            fn (Node $node) => $node instanceof Node\Expr\MethodCall
                && $node->var instanceof Node\Expr\Variable
                && Str::contains($this->getPossibleParamType($methodNode, $node->var), 'Request')// === Request::class
                && $node->name->name === 'validate'
        );
        $validationRules = $callToValidate->args[0] ?? null;

        if (! $validationRules) {
            // $this->validate($request, $rules), rules are second param. First should be $request, but no way to check type. So relying on convention.
            $callToValidate = (new NodeFinder())->findFirst(
                $methodNode,
                fn (Node $node) => $node instanceof Node\Expr\MethodCall
                    && count($node->args) === 2
                    && $node->var instanceof Node\Expr\Variable && $node->var->name === 'this'
                    && $node->name instanceof Node\Identifier && $node->name->name === 'validate'
                    && $node->args[0]->value instanceof Node\Expr\Variable
                    && Str::contains($this->getPossibleParamType($methodNode, $node->args[0]->value), 'Request')// === Request::class
                    && $node->name->name === 'validate'
            );
            $validationRules = $callToValidate->args[1] ?? null;
        }

        if ($validationRules) {
            $printer = new \PhpParser\PrettyPrinter\Standard();
            $validationRulesCode = $printer->prettyPrint([$validationRules]);

            $validationRulesCode = Str::replace(
                [...array_map(fn ($c) => "$c::", array_keys($aliases)), ...array_map(fn ($c) => "$c(", array_keys($aliases))],
                [...array_map(fn ($c) => "$c::", array_values($aliases)), ...array_map(fn ($c) => "$c(", array_values($aliases))],
                $validationRulesCode,
            );

            try {
                $rules = eval("\$request = request(); return $validationRulesCode;");
            } catch (\Throwable $exception) {
                throw $exception;
//                dump(['err validation eval' => $validationRulesCode]);
//                return null;
            }
        }

        return $rules ?? null;
    }

    private function getPossibleParamType(Node\Stmt\ClassMethod $methodNode, Node\Expr\Variable $node): ?string
    {
        $paramsMap = collect($methodNode->getParams())
            ->mapWithKeys(function (Node\Param $param) {
                try {
                    return [
                        $param->var->name => implode('\\', $param->type->parts ?? []),
                    ];
                } catch (\Throwable $exception) {
                    throw $exception;
                    dd($exception->getMessage(), $param);
                }
            })
            ->toArray();

        return $paramsMap[$node->name] ?? null;
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
        $nameContext = $nameResolver->getNameContext();

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
