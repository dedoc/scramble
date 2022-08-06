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
use Dedoc\Documentor\Support\Generator\Types\StringType;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

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
            ->filter(fn (Route $route) => ($name = $route->getAction('as')) && ! Str::startsWith($name, 'documentor'))
            ->filter(fn (Route $route) => in_array('api', $route->gatherMiddleware()))
            ->values();
    }

    private function routeToOperation(Route $route)
    {
        $operation = Operation::make(strtolower($route->methods()[0]))
            // @todo: Not always correct/expected in real projects.
            ->setOperationId(Str::camel($route->getAction('as')))
            ->setTags(
                // @todo: Fix real mess in the real projects
                $route->getAction('controller')
                    ? [Str::of(get_class($route->controller))->explode('\\')->mapInto(Stringable::class)->last()->replace('Controller', '')]
                    : []
            )
            // @todo: Figure out when params are for the implicit/explicit model binding and type them appropriately
            // @todo: Use route function typehints to get the primitive types
            ->setParameters(array_map(
                fn (string $paramName) => Parameter::make($paramName, 'path')->setSchema(Schema::fromType(new StringType)),
                $route->parameterNames()
            ));

        $operation
            ->addRequestBodyObject(
                RequestBodyObject::make()
                    ->setContent(
                        'application/json',
                        Schema::createFromArray([])
                    )
            );

        $operation
            ->addResponse(
                Response::make(200)
                    ->setContent(
                        'application/json',
                        Schema::createFromArray([])
                    )
            );

        return $operation;
    }
}
