<?php

namespace Dedoc\Scramble;

use Closure;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Exceptions\RouteAware;
use Dedoc\Scramble\OpenApiVisitor\SchemaEnforceVisitor;
use Dedoc\Scramble\Support\ContainerUtils;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Generator\UniqueNameOptions;
use Dedoc\Scramble\Support\Generator\UniqueNamesOptionsCollection;
use Dedoc\Scramble\Support\OperationBuilder;
use Dedoc\Scramble\Support\ServerFactory;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionException;
use ReflectionMethod;
use Throwable;

class Generator
{
    protected bool $throwExceptions = true;

    public ?OpenApiContext $context = null;

    public function __construct(
        private OperationBuilder $operationBuilder,
    ) {}

    public function setThrowExceptions(bool $throwExceptions): static
    {
        $this->throwExceptions = $throwExceptions;

        return $this;
    }

    public function __invoke(?GeneratorConfig $config = null)
    {
        $config ??= Scramble::getGeneratorConfig(Scramble::DEFAULT_API);

        $openApi = $this->makeOpenApi($config);
        $context = $this->context = new OpenApiContext($openApi, $config, diagnostics: new DiagnosticsCollector(throwOnError: $this->throwExceptions));
        $typeTransformer = $this->buildTypeTransformer($context);

        $operations = $this->generateOperations($context, $typeTransformer);

        $this->setUniqueOperationId($operations);
        $operations->each(fn (Operation $operation) => $openApi->addPath(
            Path::make(
                (string) Str::of($operation->path)
                    ->replaceStart($config->get('api_path', 'api'), '')
                    ->trim('/')
            )->addOperation($operation)
        ));
        $this->moveSameAlternativeServersToPath($openApi);

        $this->applyDocumentTransformers($context, $typeTransformer);

        return $openApi->toArray();
    }

    private function generateOperations(OpenApiContext $context, TypeTransformer $typeTransformer): Collection
    {
        return $this->getRoutes($context->config)
            ->flatMap(function (Route $route, int $index) use ($context, $typeTransformer) {
                try {
                    $operations = $this->routeToOperations($context, $route, $typeTransformer);

                    foreach ($operations as $i => $operation) {
                        if ($route->getAction('uses') instanceof Closure) {
                            $operation->setAttribute('isClosure', true);
                        }

                        $operation->setAttribute('index', $index + $i);
                    }

                    return $operations;
                } catch (Throwable $e) {
                    if ($e instanceof RouteAware) {
                        $e->setRoute($route);
                    }

                    if (config('app.debug', false)) {
                        $method = implode('|', $route->methods());
                        $action = $route->getAction('uses');
                        if ($action instanceof Closure) {
                            $action = '{closure}';
                        }

                        dump("Error when analyzing route '$method $route->uri' ($action): {$e->getMessage()} – ".($e->getFile().' on line '.$e->getLine()));
                        logger()->error("Error when analyzing route '$method $route->uri' ($action): {$e->getMessage()} – ".($e->getFile().' on line '.$e->getLine()));
                    }

                    throw $e;
                }
            })
            ->filter()
            ->sortBy($this->createOperationsSorter());
    }

    private function createOperationsSorter(): array
    {
        $defaultSortValue = fn (Operation $o) => $o->tags[0] ?? null;

        return [
            fn (Operation $a, Operation $b) => $a->getAttribute('groupWeight', INF) <=> $b->getAttribute('groupWeight', INF),
            fn (Operation $a, Operation $b) => $a->getAttribute('weight', INF) <=> $b->getAttribute('weight', INF), // @todo manual endpoint sorting
            fn (Operation $a, Operation $b) => $defaultSortValue($a) <=> $defaultSortValue($b),
            fn (Operation $a, Operation $b) => $a->getAttribute('index', INF) <=> $b->getAttribute('index', INF),
        ];
    }

    private function makeOpenApi(GeneratorConfig $config)
    {
        $openApi = OpenApi::make('3.1.0')
            ->setComponents(new Components)
            ->setInfo(
                InfoObject::make($config->get('ui.title', $default = config('app.name')) ?: $default)
                    ->setVersion($config->get('info.version', '0.0.1'))
                    ->setDescription($config->get('info.description', ''))
            );

        [$defaultProtocol] = explode('://', url('/'));
        $servers = $config->get('servers') ?: [
            '' => ($domain = $config->get('api_domain'))
                ? $defaultProtocol.'://'.$domain.'/'.$config->get('api_path', 'api')
                : $config->get('api_path', 'api'),
        ];
        foreach ($servers as $description => $url) {
            $openApi->addServer(
                (new ServerFactory($config->serverVariables->all()))->make(url($url ?: '/'), $description)
            );
        }

        return $openApi;
    }

    private function applyDocumentTransformers(OpenApiContext $context, TypeTransformer $typeTransformer): void
    {
        foreach ($context->config->documentTransformers->all() as $openApiTransformer) {
            $openApiTransformer = is_callable($openApiTransformer)
                ? $openApiTransformer
                : ContainerUtils::makeContextable($openApiTransformer, [
                    TypeTransformer::class => $typeTransformer,
                ]);

            if (is_callable($openApiTransformer)) {
                $openApiTransformer($context->openApi, $context);

                continue;
            }

            if ($openApiTransformer instanceof DocumentTransformer) {
                $openApiTransformer->handle($context->openApi, $context);

                continue;
            }

            // @phpstan-ignore deadCode.unreachable
            throw new InvalidArgumentException('(callable(OpenApi, OpenApiContext): void)|DocumentTransformer type for document transformer expected, received '.$openApiTransformer::class);
        }
    }

    /**
     * @return Collection<int, Route>
     */
    private function getRoutes(GeneratorConfig $config): Collection
    {
        return collect(RouteFacade::getRoutes())
            ->pipe(function (Collection $c) {
                $onlyRoutes = $c->filter(function (Route $route) {

                    if (! is_string($route->getAction('controller'))) {
                        return false;
                    }

                    if (! is_string($route->getAction('uses'))) {
                        return false;
                    }

                    try {
                        $reflection = new ReflectionMethod(...explode('@', $route->getAction('uses')));

                        if (str_contains($reflection->getDocComment() ?: '', '@only-docs')) {
                            return true;
                        }
                    } catch (Throwable) {
                    }

                    return false;
                });

                return $onlyRoutes->count() ? $onlyRoutes : $c;
            })
            ->filter(function (Route $route) {
                return ! ($name = $route->getAction('as')) || ! Str::startsWith($name, 'scramble');
            })
            ->filter($config->routes())
            ->filter(function (Route $route) {
                if (! is_string($route->getAction('uses'))) {
                    return true;
                }

                try {
                    $reflection = new ReflectionMethod(...explode('@', $route->getAction('uses')));
                } catch (ReflectionException) {
                    /*
                     * If route is registered but route method doesn't exist, it will not be included
                     * in the resulting documentation.
                     */
                    return false;
                }

                if (count($reflection->getAttributes(ExcludeRouteFromDocs::class))) {
                    return false;
                }

                if (count($reflection->getDeclaringClass()->getAttributes(ExcludeAllRoutesFromDocs::class))) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    private function buildTypeTransformer(OpenApiContext $context): TypeTransformer
    {
        return app()->make(TypeTransformer::class, [
            'context' => $context,
        ]);
    }

    /** @return Operation[] */
    private function routeToOperations(OpenApiContext $context, Route $route, TypeTransformer $typeTransformer): array
    {
        $operations = $this->operationBuilder->buildAll($context, $route, $typeTransformer);

        foreach ($operations as $operation) {
            $this->ensureSchemaTypes($context, $route, $operation);
        }

        return $operations;
    }

    private function ensureSchemaTypes(OpenApiContext $context, Route $route, Operation $operation): void
    {
        if (! Scramble::getSchemaValidator()->hasRules()) {
            return;
        }

        [$traverser, $visitor] = $this->createSchemaEnforceTraverser($route, $context);

        $traverser->traverse($operation, ['', 'paths', $operation->path, $operation->method]);
        $references = $visitor->popReferences();

        /** @var Reference $ref */
        foreach ($references as $ref) {
            if ($resolvedType = $ref->resolve()) {
                $traverser->traverse($resolvedType, ['', 'components', $ref->referenceType, $ref->getUniqueName()]);
            }
        }
    }

    /**
     * @return array{OpenApiTraverser, SchemaEnforceVisitor}
     */
    private function createSchemaEnforceTraverser(Route $route, OpenApiContext $context): array
    {
        $traverser = new OpenApiTraverser([$visitor = new SchemaEnforceVisitor($route, $context->diagnostics->forRoute($route)->forCategory('Schema validation')->forContext('SchemaEnforceVisitor'))]);

        return [$traverser, $visitor];
    }

    private function moveSameAlternativeServersToPath(OpenApi $openApi): void
    {
        foreach (collect($openApi->paths)->groupBy('path') as $pathsGroup) {
            if ($pathsGroup->isEmpty()) {
                continue;
            }

            $operations = collect($pathsGroup->pluck('operations')->flatten());

            $operationsHaveSameAlternativeServers = $operations->count()
                && $operations->every(fn (Operation $o) => count($o->servers))
                && $operations->unique(function (Operation $o) {
                    return collect($o->servers)->map(fn (Server $s) => $s->url)->join('.');
                })->count() === 1;

            if (! $operationsHaveSameAlternativeServers) {
                continue;
            }

            $pathsGroup->every->servers($operations->first()->servers);

            foreach ($operations as $operation) {
                $operation->servers([]);
            }
        }
    }

    /**
     * @param  Collection<int, Operation>  $operations
     */
    private function setUniqueOperationId(Collection $operations): void
    {
        $names = new UniqueNamesOptionsCollection;

        $operations->each(function (Operation $operation) use ($names) {
            if ($operation->operationId) {
                return;
            }

            $names->push($operation->getAttribute('operationId')); // @phpstan-ignore argument.type
        });

        $operations->each(function (Operation $operation, $index) use ($names) {
            if ($operation->operationId) {
                return;
            }

            $name = $operation->getAttribute('operationId');
            if (! $name instanceof UniqueNameOptions) {
                return;
            }

            if (! $name->eloquent && $operation->getAttribute('isClosure')) {
                return;
            }

            $operation->setOperationId($names->getUniqueName($name, function (string $fallback) use ($index) {
                return "{$fallback}_{$index}";
            }));
        });
    }
}
